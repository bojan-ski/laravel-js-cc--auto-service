<?php

namespace Tests\Feature\JobItems;

use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobPartLineRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->post(route('jobs.parts.store', $job), ['part_id' => $this->stockedPart()->getKey(), 'quantity' => 1])
            ->assertRedirect(route('login'));

        $this->assertSame(0, JobItem::query()->count());
    }

    #[DataProvider('roleProvider')]
    public function test_every_role_may_add_a_part_line(string $role): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart();

        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('jobs.parts.store', $job), ['part_id' => $part->getKey(), 'quantity' => 2])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.items.flash.part_added'))
            ->assertSessionMissing('warning');

        $this->assertSame(1, JobItem::query()->count());
        $this->assertSame(8, $part->fresh()->quantity_in_stock);
    }

    public function test_a_mechanics_submitted_assignee_is_overwritten_with_themselves(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $colleague = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $this->actingAs($mechanic)
            ->post(route('jobs.parts.store', $job), [
                'part_id' => $this->stockedPart()->getKey(),
                'quantity' => 1,
                'user_id' => $colleague->getKey(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($mechanic->getKey(), JobItem::query()->sole()->user_id);
    }

    public function test_adding_a_part_line_to_a_terminal_job_is_refused(): void
    {
        $job = Job::factory()->completed()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.parts.store', $job), ['part_id' => $this->stockedPart()->getKey(), 'quantity' => 1])
            ->assertForbidden();

        $this->assertSame(0, JobItem::query()->count());
    }

    public function test_a_mechanic_may_edit_and_delete_their_own_part_line(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $item = $this->partLine($mechanic);

        $this->actingAs($mechanic)
            ->put(route('job-items.update', $item), ['quantity' => '4'])
            ->assertSessionHasNoErrors();

        $this->assertSame('4.00', $item->fresh()->quantity);

        $this->actingAs($mechanic)
            ->delete(route('job-items.destroy', $item))
            ->assertRedirect(route('jobs.show', $item->job_id));

        $this->assertNull(JobItem::query()->find($item->getKey()));
    }

    public function test_a_mechanic_is_refused_on_a_colleagues_part_line(): void
    {
        $item = $this->partLine(User::factory()->mechanic()->create());
        $intruder = User::factory()->mechanic()->create();

        $this->actingAs($intruder)
            ->put(route('job-items.update', $item), ['quantity' => '9'])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('job-items.destroy', $item))
            ->assertForbidden();

        $this->assertSame('2.00', $item->fresh()->quantity);
        $this->assertSame(0, StockMovement::query()->whereIn('reason', [StockMovementReason::JobReturn])->count());
    }

    public function test_front_office_may_edit_and_delete_a_line_they_do_not_own(): void
    {
        $item = $this->partLine(User::factory()->mechanic()->create());

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), ['quantity' => '5', 'description' => 'Adjusted'])
            ->assertSessionHasNoErrors();

        $this->assertSame('5.00', $item->fresh()->quantity);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('job-items.destroy', $item))
            ->assertSessionHasNoErrors();

        $this->assertNull(JobItem::query()->find($item->getKey()));
    }

    /**
     * Deleting ->visibleTo() from JobPartLineController::store leaves every other
     * assertion in this suite green: the scope only narrows the select list, and
     * addPart() reads nothing from the instance but its key and name.
     */
    #[DataProvider('roleProvider')]
    public function test_the_controllers_part_read_selects_only_the_columns_the_role_may_see(string $role): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart();

        DB::enableQueryLog();
        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('jobs.parts.store', $job), ['part_id' => $part->getKey(), 'quantity' => 1])
            ->assertSessionHasNoErrors();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $reads = array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`')
                && ! str_starts_with($query, 'select count'),
        ));

        $this->assertNotEmpty($reads, implode("\n", $queries));

        $controllerRead = $reads[0];

        if ($role === 'admin') {
            $this->assertStringStartsWith('select * from `parts`', $controllerRead);

            return;
        }

        // `select *` names no columns, so a "does not contain unit_price" assertion
        // would pass vacuously against an unscoped read. Demand the column list first.
        $this->assertStringNotContainsString('select *', $controllerRead, 'The read was not scoped at all.');
        $this->assertStringContainsString('`sku`', $controllerRead);
        $this->assertStringNotContainsString('cost_price', $controllerRead);

        if ($role === 'mechanic') {
            $this->assertStringNotContainsString('unit_price', $controllerRead);
        } else {
            $this->assertStringContainsString('unit_price', $controllerRead);
        }
    }

    /**
     * The edit and delete paths resolve the part again inside the service. That read
     * must be scoped the same way the controller's is, or `$item->part` carries a
     * different column set depending on which path loaded it.
     */
    public function test_the_services_part_read_is_scoped_on_the_edit_and_delete_paths(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $item = $this->partLine($mechanic);

        $this->assertPartReadIsScoped(fn () => $this->actingAs($mechanic)
            ->put(route('job-items.update', $item), ['quantity' => '5'])
            ->assertSessionHasNoErrors());

        $this->assertPartReadIsScoped(fn () => $this->actingAs($mechanic)
            ->delete(route('job-items.destroy', $item))
            ->assertSessionHasNoErrors());
    }

    private function assertPartReadIsScoped(callable $act): void
    {
        DB::enableQueryLog();
        $act();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        $reads = array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`')
                && ! str_starts_with($query, 'select count'),
        ));

        $this->assertNotEmpty($reads, implode("\n", $queries));
        $this->assertStringNotContainsString('select *', $reads[0], implode("\n", $queries));
        $this->assertStringNotContainsString('unit_price', $reads[0]);
        $this->assertStringNotContainsString('cost_price', $reads[0]);
    }

    public function test_a_part_line_added_by_a_mechanic_still_snapshots_the_catalogue_price(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(['unit_price' => '4200.00']);

        $this->actingAs(User::factory()->mechanic()->create())
            ->post(route('jobs.parts.store', $job), ['part_id' => $part->getKey(), 'quantity' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('4200.00', JobItem::query()->sole()->unit_price);
    }

    public function test_deleting_a_part_line_through_the_route_returns_its_stock(): void
    {
        $actor = User::factory()->receptionist()->create();
        $part = $this->stockedPart();
        $item = $this->partLine($actor, $part);

        $this->assertSame(8, $part->fresh()->quantity_in_stock);

        $this->actingAs($actor)
            ->delete(route('job-items.destroy', $item))
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertSame(1, StockMovement::query()->where('reason', StockMovementReason::JobReturn)->count());
    }

    public function test_cancelling_through_the_route_returns_stock_and_credits_the_acting_user(): void
    {
        $actor = User::factory()->receptionist()->create();
        $canceller = User::factory()->admin()->create();
        $part = $this->stockedPart();
        $item = $this->partLine($actor, $part);

        $this->actingAs($canceller)
            ->patch(route('jobs.cancel', $item->job_id))
            ->assertSessionHasNoErrors();

        $return = StockMovement::query()->where('reason', StockMovementReason::JobReturn)->sole();

        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertSame($canceller->getKey(), $return->user_id);
        $this->assertNotNull($item->fresh());
    }

    public function test_the_movement_records_who_caused_it_even_when_the_line_is_unassigned(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $job = Job::factory()->inProgress()->create();

        $this->actingAs($receptionist)
            ->post(route('jobs.parts.store', $job), ['part_id' => $this->stockedPart()->getKey(), 'quantity' => 1]);

        $movement = StockMovement::query()->where('reason', StockMovementReason::JobUse)->sole();

        $this->assertSame($receptionist->getKey(), $movement->user_id);
        $this->assertNull(JobItem::query()->sole()->user_id);
    }

    private function partLine(User $owner, ?Part $part = null): JobItem
    {
        return app(JobItemService::class)->addPart(
            Job::factory()->inProgress()->create(),
            $part ?? $this->stockedPart(),
            $owner,
            ['quantity' => 2],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function stockedPart(array $attributes = []): Part
    {
        $part = Part::factory()->create($attributes);

        app(StockService::class)->record(
            $part,
            10,
            StockMovementReason::Restock,
            User::factory()->receptionist()->create(),
        );

        return $part;
    }
}
