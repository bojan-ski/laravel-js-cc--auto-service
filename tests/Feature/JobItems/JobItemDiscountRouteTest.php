<?php

namespace Tests\Feature\JobItems;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobItemDiscountRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_front_office_discounts_a_line_on_a_working_job_and_nothing_else_changes(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($mechanic)->create([
            'description' => 'Brake service',
            'quantity' => '1.50',
            'unit_price' => '3000.00',
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '10'])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.items.flash.discounted'));

        $fresh = $item->fresh();

        $this->assertSame('10.00', $fresh->discount_percent);
        $this->assertSame('1.50', $fresh->quantity);
        $this->assertSame('3000.00', $fresh->unit_price);
        $this->assertSame('Brake service', $fresh->description);
        $this->assertSame($mechanic->getKey(), $fresh->user_id);
        $this->assertSame('4050.00', $fresh->lineTotal());
    }

    public function test_front_office_discounts_a_part_line_on_a_completed_unlocked_job_without_moving_stock(): void
    {
        $part = Part::factory()->create();
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->part()->forPart($part)->create(['quantity' => 2]);
        $stockBefore = Part::query()->whereKey($part->getKey())->value('quantity_in_stock');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '100'])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.items.flash.discounted'));

        $fresh = $item->fresh();

        $this->assertSame('100.00', $fresh->discount_percent);
        $this->assertSame('2.00', $fresh->quantity);
        $this->assertSame('0.00', $fresh->lineTotal());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame($stockBefore, Part::query()->whereKey($part->getKey())->value('quantity_in_stock'));
    }

    public function test_setting_zero_removes_the_discount_and_restores_the_gross(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->discounted('12.50')->create(['quantity' => '1.00', 'unit_price' => '33.33']);

        $this->assertSame('29.16', $item->lineTotal());

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '0'])
            ->assertRedirect(route('jobs.show', $job));

        $fresh = $item->fresh();

        $this->assertFalse($fresh->hasDiscount());
        $this->assertSame('33.33', $fresh->lineTotal());
        $this->assertSame($fresh->grossTotal(), $fresh->lineTotal());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPercentages(): array
    {
        return [
            'empty' => [''],
            'missing' => [null],
            'negative' => ['-1'],
            'above one hundred' => ['100.01'],
            'three decimals' => ['10.555'],
            'not a number' => ['ten'],
            'exponent notation' => ['1e1'],
        ];
    }

    #[DataProvider('invalidPercentages')]
    public function test_an_invalid_percentage_is_refused_and_nothing_changes(mixed $percent): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->discounted('5.00')->create();

        $payload = $percent === null ? [] : ['discount_percent' => $percent];

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('job-items.discount.update', $item), $payload)
            ->assertSessionHasErrors('discount_percent');

        $this->assertSame('5.00', $item->fresh()->discount_percent);
    }

    public function test_the_boundaries_zero_and_one_hundred_with_two_decimals_are_accepted(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $receptionist = User::factory()->receptionist()->create();

        foreach (['0', '100', '99.99', '0.01'] as $percent) {
            $this->actingAs($receptionist)
                ->patch(route('job-items.discount.update', $item), ['discount_percent' => $percent])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('0.01', $item->fresh()->discount_percent);
    }

    public function test_the_error_renders_under_the_field_in_the_reopened_panel(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $panel = route('jobs.show', ['job' => $job, 'discount' => $item->getKey()]);

        $this->actingAs(User::factory()->receptionist()->create());

        $this->from($panel)
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '150'])
            ->assertRedirect($panel);

        $reopened = $this->get($panel)
            ->assertOk()
            ->assertSee('id="discount_percent-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertDontSee('aria-describedby="discount_percent-hint"', false)
            ->assertSee(e(__('validation.max.numeric', ['attribute' => __('jobs.items.discount.percent'), 'max' => 100])), false);

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="discount_percent"[^>]*value="150"/s',
            $reopened->getContent(),
            'The reopened panel dropped the value the user typed.',
        );
    }

    public function test_a_mechanic_is_refused_on_their_own_line_and_on_a_colleagues(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $own = JobItem::factory()->forJob($job)->performedBy($mechanic)->create();
        $colleagues = JobItem::factory()->forJob($job)->create();

        foreach ([$own, $colleagues] as $item) {
            $this->actingAs($mechanic)
                ->patch(route('job-items.discount.update', $item), ['discount_percent' => '10'])
                ->assertForbidden();

            $this->assertSame('0.00', $item->fresh()->discount_percent);
        }
    }

    public function test_front_office_is_refused_on_a_locked_job(): void
    {
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $job->forceFill(['locked_at' => now()->subMinutes(10)])->saveQuietly();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '10'])
            ->assertForbidden();

        $this->assertSame('0.00', $item->fresh()->discount_percent);
    }

    public function test_front_office_is_refused_on_a_cancelled_job(): void
    {
        $job = Job::factory()->cancelled()->create();
        $item = JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '10'])
            ->assertForbidden();

        $this->assertSame('0.00', $item->fresh()->discount_percent);
    }

    public function test_an_unknown_line_is_not_found(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('job-items.discount.update', 999999), ['discount_percent' => '10'])
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $item = JobItem::factory()->create();

        $this->patch(route('job-items.discount.update', $item), ['discount_percent' => '10'])
            ->assertRedirect(route('login'));

        $this->assertSame('0.00', $item->fresh()->discount_percent);
    }

    public function test_a_completed_unlocked_job_accepts_the_discount_and_no_other_line_change(): void
    {
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist);

        $this->put(route('job-items.update', $item), [
            'description' => 'Changed',
            'quantity' => '2.00',
            'user_id' => $item->user_id,
        ])->assertForbidden();

        $this->delete(route('job-items.destroy', $item))->assertForbidden();

        $this->post(route('jobs.items.store', $job), [
            'description' => 'Extra',
            'quantity' => '1.00',
            'user_id' => $item->user_id,
        ])->assertForbidden();

        $this->post(route('jobs.parts.store', $job), [
            'part_id' => Part::factory()->create()->getKey(),
            'quantity' => '1',
        ])->assertForbidden();

        $this->assertSame(1, JobItem::query()->count());
        $this->assertNotSame('Changed', $item->fresh()->description);

        $this->patch(route('job-items.discount.update', $item), ['discount_percent' => '10'])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame('10.00', $item->fresh()->discount_percent);
    }

    public function test_a_discount_key_on_the_other_line_routes_is_ignored_for_every_role(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();

        foreach (['mechanic' => $mechanic, 'receptionist' => User::factory()->receptionist()->create(), 'admin' => User::factory()->admin()->create()] as $role => $user) {
            $this->actingAs($user)
                ->post(route('jobs.items.store', $job), [
                    'description' => "Labor by {$role}",
                    'quantity' => '1.00',
                    'user_id' => $mechanic->getKey(),
                    'discount_percent' => '50',
                ])
                ->assertRedirect(route('jobs.show', $job));

            $this->actingAs($user)
                ->post(route('jobs.parts.store', $job), [
                    'part_id' => Part::factory()->create()->getKey(),
                    'quantity' => '1',
                    'discount_percent' => '50',
                ])
                ->assertRedirect(route('jobs.show', $job));
        }

        $own = JobItem::factory()->forJob($job)->performedBy($mechanic)->create();

        $this->actingAs($mechanic)
            ->put(route('job-items.update', $own), [
                'description' => 'Edited',
                'quantity' => '2.00',
                'discount_percent' => '50',
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('job-items.update', $own), [
                'description' => 'Edited again',
                'quantity' => '2.00',
                'user_id' => $mechanic->getKey(),
                'discount_percent' => '50',
            ])
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(7, JobItem::query()->count());
        $this->assertSame(['0.00'], JobItem::query()->pluck('discount_percent')->unique()->values()->all());
        $this->assertSame('Edited again', $own->fresh()->description);
    }

    public function test_a_mechanics_edit_of_their_own_discounted_line_keeps_the_discount(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();
        $item = JobItem::factory()->forJob($job)->performedBy($mechanic)->discounted('10.00')->create([
            'quantity' => '1.50',
            'unit_price' => '3000.00',
        ]);

        $this->actingAs($mechanic)
            ->put(route('job-items.update', $item), [
                'description' => 'Brake service, both axles',
                'quantity' => '2.00',
            ])
            ->assertRedirect(route('jobs.show', $job));

        $fresh = $item->fresh();

        $this->assertSame('Brake service, both axles', $fresh->description);
        $this->assertSame('10.00', $fresh->discount_percent);
        $this->assertSame('5400.00', $fresh->lineTotal());
    }
}
