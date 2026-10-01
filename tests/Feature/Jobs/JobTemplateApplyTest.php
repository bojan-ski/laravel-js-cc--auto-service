<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobItemType;
use App\Enums\StockMovementReason;
use App\Exceptions\JobLockedException;
use App\Exceptions\JobNotAcceptingLineItems;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class JobTemplateApplyTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function frontOfficeProvider(): array
    {
        return ['receptionist' => ['receptionist'], 'admin' => ['admin']];
    }

    public function test_a_mechanic_applies_a_template_and_gets_every_line_in_order_at_todays_prices(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $oil = $this->stockedPart('Engine oil 5W-30, 1 l', '14.50', 20);
        $filter = $this->stockedPart('Oil filter', '12.90', 10);

        $template = $this->template('Small service', [
            ['labor', 'Oil and filter service', '1.50'],
            ['part', $filter, 2],
            ['part', $oil, 5],
        ]);

        $filter->update(['name' => 'Oil filter (Mann)']);

        $this->actingAs($mechanic)
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', 'Small service applied: 3 lines added.');

        $lines = JobItem::query()->where('job_id', $job->getKey())->orderBy('id')->get();

        $this->assertSame(
            [
                ['labor', null, 'Oil and filter service', '1.50', number_format((float) config('shop.labor_rate'), 2, '.', '')],
                ['part', $filter->getKey(), 'Oil filter (Mann)', '2.00', '12.90'],
                ['part', $oil->getKey(), 'Engine oil 5W-30, 1 l', '5.00', '14.50'],
            ],
            $lines->map(fn (JobItem $line): array => [
                $line->type->value, $line->part_id, $line->description, $line->quantity, $line->unit_price,
            ])->all(),
        );
        $this->assertSame([$mechanic->getKey()], $lines->pluck('user_id')->unique()->values()->all());

        $movements = StockMovement::query()->where('reason', StockMovementReason::JobUse)->orderBy('id')->get();
        $this->assertSame([-2, -5], $movements->pluck('quantity_change')->all());
        $this->assertSame([$mechanic->getKey()], $movements->pluck('user_id')->unique()->values()->all());
        $this->assertSame(8, $filter->fresh()->quantity_in_stock);
        $this->assertSame(15, $oil->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_mechanic_who_submits_a_colleagues_id_still_gets_the_lines(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00'], ['part', $this->stockedPart('Pads', '54.00', 4), 1]]);

        $this->actingAs($mechanic)
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey(), 'user_id' => $colleague->getKey()])
            ->assertSessionHasNoErrors();

        $this->assertSame([$mechanic->getKey()], JobItem::query()->where('job_id', $job->getKey())->pluck('user_id')->unique()->values()->all());
    }

    #[DataProvider('frontOfficeProvider')]
    public function test_front_office_assigns_every_line_labor_and_part_to_the_chosen_mechanic(string $role): void
    {
        $desk = User::factory()->{$role}()->create();
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart('Pads', '54.00', 4);
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00'], ['part', $part, 1]]);

        $this->actingAs($desk)
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey(), 'user_id' => $mechanic->getKey()])
            ->assertSessionHasNoErrors();

        $lines = JobItem::query()->where('job_id', $job->getKey())->orderBy('id')->get();

        $this->assertSame([JobItemType::Labor, JobItemType::Part], $lines->pluck('type')->all());
        $this->assertSame([$mechanic->getKey(), $mechanic->getKey()], $lines->pluck('user_id')->all());
        $this->assertSame($desk->getKey(), StockMovement::query()->where('part_id', $part->getKey())->where('reason', StockMovementReason::JobUse)->sole()->user_id);
    }

    public function test_front_office_applies_a_parts_only_template_without_a_mechanic_and_the_lines_are_unassigned(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Wipers', [['part', $this->stockedPart('Wiper 24"', '24.00', 4), 2]]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertSessionHasNoErrors();

        $this->assertNull(JobItem::query()->where('job_id', $job->getKey())->sole()->user_id);
    }

    public function test_front_office_must_choose_a_mechanic_for_a_template_with_labor(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00']]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertSessionHasErrors(['user_id' => __('templates.apply.errors.mechanic_required')]);

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_front_office_cannot_assign_the_lines_to_a_deleted_or_unknown_mechanic(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00']]);
        $receptionist = User::factory()->receptionist()->create();
        $offboarded = User::factory()->mechanic()->create();
        $offboarded->delete();

        foreach ([$offboarded->getKey(), 999999] as $userId) {
            $this->actingAs($receptionist)
                ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey(), 'user_id' => $userId])
                ->assertSessionHasErrors('user_id');
        }

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_the_service_refuses_front_office_without_a_mechanic_for_labor(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00']]);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(JobItemService::class)->applyTemplate($job, $template, User::factory()->receptionist()->create(), null);
        } finally {
            $this->assertSame(0, JobItem::query()->count());
        }
    }

    public function test_the_service_refuses_a_deleted_template(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00'], ['part', $this->stockedPart('Pads', '54.00', 4), 1]]);
        $template->delete();
        $movementsBefore = StockMovement::query()->count();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(JobItemService::class)->applyTemplate($job, $template, User::factory()->mechanic()->create(), null);
        } finally {
            $this->assertSame(0, JobItem::query()->count());
            $this->assertSame($movementsBefore, StockMovement::query()->count());
        }
    }

    /**
     * The route resolves the template before the job row is locked; a template
     * deleted in that window must still be refused, so the check reads the row
     * under the lock rather than the instance it was handed (spec 36 review).
     */
    public function test_a_template_deleted_after_it_was_resolved_is_refused(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00'], ['part', $this->stockedPart('Pads', '54.00', 4), 1]]);
        JobTemplate::query()->findOrFail($template->getKey())->delete();
        $movementsBefore = StockMovement::query()->count();

        $this->assertFalse($template->trashed(), 'The instance handed to the service must still read as live.');
        $this->expectException(InvalidArgumentException::class);

        try {
            app(JobItemService::class)->applyTemplate($job, $template, User::factory()->mechanic()->create(), null);
        } finally {
            $this->assertSame(0, JobItem::query()->count());
            $this->assertSame($movementsBefore, StockMovement::query()->count());
        }
    }

    public function test_a_template_with_a_deleted_part_writes_nothing_and_names_the_part(): void
    {
        $job = Job::factory()->inProgress()->create();
        $good = $this->stockedPart('Air filter', '23.50', 5);
        $retired = $this->stockedPart('Oil filter (discontinued)', '5.90', 3);
        $template = $this->template('Budget service', [['labor', 'Oil service', '1.00'], ['part', $good, 1], ['part', $retired, 1]]);
        $retired->delete();
        $movementsBefore = StockMovement::query()->count();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->from(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()]);

        $response->assertRedirect(route('jobs.show', ['job' => $job, 'add' => 'template']).'#line-item-form')
            ->assertSessionHasErrors(['job_template_id' => __('templates.apply.errors.deleted_part', ['part' => 'Oil filter (discontinued)'])])
            ->assertSessionHasInput('job_template_id', $template->getKey());

        $this->assertSame(0, JobItem::query()->count());
        $this->assertSame($movementsBefore, StockMovement::query()->count());
        $this->assertSame(5, $good->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_the_refusal_renders_under_the_template_select_with_the_choice_kept(): void
    {
        $job = Job::factory()->inProgress()->create();
        $retired = $this->stockedPart('Oil filter (discontinued)', '5.90', 3);
        $other = $this->template('Another', [['labor', 'Check', '0.50']]);
        $template = $this->template('Budget service', [['part', $retired, 1]]);
        $retired->delete();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertRedirect();

        $html = $this->actingAs($mechanic)
            ->get(route('jobs.show', ['job' => $job, 'add' => 'template']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="job_template_id-error"', $html);
        $this->assertStringContainsString(e(__('templates.apply.errors.deleted_part', ['part' => 'Oil filter (discontinued)'])), $html);
        $this->assertMatchesRegularExpression('/<option value="'.$template->getKey().'"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="'.$other->getKey().'"\s+selected/', $html);
    }

    public function test_a_trashed_template_an_unknown_id_and_a_template_without_lines_are_refused(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $trashed = $this->template('Old', [['labor', 'Check', '1.00']]);
        $trashed->delete();
        $empty = JobTemplate::factory()->create();

        $this->actingAs($mechanic)->post(route('jobs.templates.apply', $job), ['job_template_id' => $trashed->getKey()])
            ->assertSessionHasErrors('job_template_id');
        $this->actingAs($mechanic)->post(route('jobs.templates.apply', $job), ['job_template_id' => 999999])
            ->assertSessionHasErrors('job_template_id');
        $this->actingAs($mechanic)->post(route('jobs.templates.apply', $job), ['job_template_id' => $empty->getKey()])
            ->assertSessionHasErrors(['job_template_id' => __('templates.apply.errors.no_lines')]);

        $this->assertSame(0, JobItem::query()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unwritableJobProvider(): array
    {
        return ['locked' => ['locked'], 'completed' => ['completed'], 'cancelled' => ['cancelled']];
    }

    #[DataProvider('unwritableJobProvider')]
    public function test_a_job_that_takes_no_lines_is_refused_by_the_route(string $state): void
    {
        $job = $state === 'locked' ? Job::factory()->completed()->locked()->create() : Job::factory()->{$state}()->create();
        $template = $this->template('Check', [['labor', 'Check', '1.00']]);

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertForbidden();

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_the_service_throws_what_a_single_line_write_throws(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $template = $this->template('Check', [['labor', 'Check', '1.00']]);
        $service = app(JobItemService::class);

        try {
            $service->applyTemplate(Job::factory()->completed()->locked()->create(), $template, $mechanic, null);
            $this->fail('A locked job must refuse the apply.');
        } catch (JobLockedException) {
            //
        }

        foreach (['completed', 'cancelled'] as $state) {
            try {
                $service->applyTemplate(Job::factory()->{$state}()->create(), $template, $mechanic, null);
                $this->fail("A {$state} job must refuse the apply.");
            } catch (JobNotAcceptingLineItems) {
                //
            }
        }

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_later_price_changes_leave_applied_lines_alone_and_a_second_apply_uses_the_new_price(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $part = $this->stockedPart('Pads', '54.00', 10);
        $template = $this->template('Brakes', [['labor', 'Brake service', '1.00'], ['part', $part, 1]]);
        $service = app(JobItemService::class);
        config(['shop.labor_rate' => '45.00']);

        $first = $service->applyTemplate($job, $template, $mechanic, null);

        $part->update(['unit_price' => '60.00']);
        config(['shop.labor_rate' => '99.00']);

        $this->assertSame(
            ['45.00', '54.00'],
            JobItem::query()->whereKey($first->pluck('id'))->orderBy('id')->pluck('unit_price')->all(),
        );

        $second = $service->applyTemplate($job, $template, $mechanic, null);

        $this->assertSame(['99.00', '60.00'], JobItem::query()->whereKey($second->pluck('id'))->orderBy('id')->pluck('unit_price')->all());
        $this->assertSame(4, JobItem::query()->where('job_id', $job->getKey())->count());
        $this->assertSame(8, $part->fresh()->quantity_in_stock);
    }

    public function test_driving_parts_below_zero_succeeds_and_warns_once_per_part_with_its_final_balance(): void
    {
        $job = Job::factory()->inProgress()->create();
        $pads = $this->stockedPart('Pads', '54.00', 1);
        $fluid = $this->stockedPart('Brake fluid', '13.50', 1);
        $plenty = $this->stockedPart('Cleaner', '5.00', 50);
        $template = $this->template('Brakes', [['part', $pads, 1], ['part', $fluid, 2], ['part', $pads, 2], ['part', $plenty, 1]]);

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', __('templates.apply.flash.stock_negative', [
                'parts' => __('templates.apply.flash.stock_negative_part', ['name' => 'Pads', 'balance' => -2])
                    .', '.__('templates.apply.flash.stock_negative_part', ['name' => 'Brake fluid', 'balance' => -1]),
            ]));

        $this->assertSame(4, JobItem::query()->where('job_id', $job->getKey())->count());
        $this->assertSame(-2, $pads->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_no_warning_is_flashed_when_stock_stays_at_or_above_zero(): void
    {
        $job = Job::factory()->inProgress()->create();
        $template = $this->template('Pads', [['part', $this->stockedPart('Pads', '54.00', 2), 2]]);

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
            ->assertSessionMissing('warning');
    }

    public function test_the_success_flash_names_the_template_and_pluralises_the_count(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $one = $this->template('Single', [['labor', 'Check', '1.00']]);
        $two = $this->template('Double', [['labor', 'Check', '1.00'], ['labor', 'Test drive', '0.50']]);

        $this->actingAs($mechanic)
            ->post(route('jobs.templates.apply', Job::factory()->inProgress()->create()), ['job_template_id' => $one->getKey()])
            ->assertSessionHas('success', 'Single applied: 1 line added.');

        $this->actingAs($mechanic)
            ->post(route('jobs.templates.apply', Job::factory()->inProgress()->create()), ['job_template_id' => $two->getKey()])
            ->assertSessionHas('success', 'Double applied: 2 lines added.');
    }

    public function test_applying_the_same_template_twice_adds_its_lines_twice(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $template = $this->template('Check', [['labor', 'Check', '1.00'], ['labor', 'Test drive', '0.50']]);

        foreach ([1, 2] as $attempt) {
            $this->actingAs($mechanic)
                ->post(route('jobs.templates.apply', $job), ['job_template_id' => $template->getKey()])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['Check', 'Test drive', 'Check', 'Test drive'],
            JobItem::query()->where('job_id', $job->getKey())->orderBy('id')->pluck('description')->all(),
        );
    }

    /**
     * @param  list<array{0: 'labor'|'part', 1: string|Part, 2: string|int}>  $lines
     */
    private function template(string $name, array $lines): JobTemplate
    {
        $template = JobTemplate::factory()->create(['name' => $name]);

        foreach ($lines as $position => [$type, $subject, $quantity]) {
            $factory = JobTemplateItem::factory()->forTemplate($template);

            $factory = $type === 'part'
                ? $factory->forPart($subject, (int) $quantity)
                : $factory->labor()->state(['description' => $subject, 'quantity' => $quantity]);

            $factory->create(['position' => $position + 1]);
        }

        return $template;
    }

    private function stockedPart(string $name, string $price, int $stock): Part
    {
        $part = Part::factory()->create(['name' => $name, 'unit_price' => $price]);

        app(StockService::class)->record($part, $stock, StockMovementReason::Restock, User::factory()->receptionist()->create());

        return $part->fresh();
    }
}
