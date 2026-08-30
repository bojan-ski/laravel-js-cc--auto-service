<?php

namespace Tests\Feature\JobItems;

use App\Enums\JobItemType;
use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class JobPartLineServiceTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    public function test_adding_a_part_line_snapshots_the_price_and_the_name(): void
    {
        $part = $this->stockedPart(10, ['name' => 'Brake pads front', 'unit_price' => '4200.00']);
        $job = Job::factory()->inProgress()->create();

        $item = $this->service()->addPart($job, $part, $this->actor(), ['quantity' => 2]);

        $this->assertSame(JobItemType::Part, $item->type);
        $this->assertSame($part->getKey(), $item->part_id);
        $this->assertSame('Brake pads front', $item->description);
        $this->assertSame('4200.00', $item->unit_price);
        $this->assertSame('2.00', $item->fresh()->quantity);
    }

    public function test_a_later_catalogue_rename_or_reprice_does_not_alter_an_existing_line(): void
    {
        $part = $this->stockedPart(10, ['name' => 'Brake pads front', 'unit_price' => '4200.00']);
        $item = $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $this->actor(),
            ['quantity' => 1],
        );

        $part->update(['name' => 'Brake pads front (2027)', 'unit_price' => '5500.00']);

        $this->assertSame('Brake pads front', $item->fresh()->description);
        $this->assertSame('4200.00', $item->fresh()->unit_price);
    }

    public function test_adding_writes_exactly_one_negative_job_use_movement_linked_to_the_line(): void
    {
        $part = $this->stockedPart(10);
        $item = $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $this->actor(),
            ['quantity' => 3],
        );

        $movements = StockMovement::query()->where('reason', StockMovementReason::JobUse)->get();

        $this->assertCount(1, $movements);
        $this->assertSame(-3, $movements->first()->quantity_change);
        $this->assertSame($item->getKey(), $movements->first()->job_item_id);
        $this->assertSame(7, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_the_submitted_description_overrides_the_part_name(): void
    {
        $part = $this->stockedPart(10, ['name' => 'Brake pads front']);

        $item = $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $this->actor(),
            ['quantity' => 1, 'description' => 'Pads, front axle only'],
        );

        $this->assertSame('Pads, front axle only', $item->description);
    }

    public function test_increasing_the_quantity_writes_one_job_use_of_the_difference(): void
    {
        $item = $this->partLine(quantity: 2, stock: 10);

        $this->service()->update($item, $this->actor(), $this->editPayload(5));

        $this->assertSame([-2, -3], $this->movementChanges(StockMovementReason::JobUse));
        $this->assertSame(
            [$item->getKey(), $item->getKey()],
            StockMovement::query()
                ->where('reason', StockMovementReason::JobUse)
                ->orderBy('id')
                ->pluck('job_item_id')
                ->all(),
            'The delta movement must link back to the line that caused it.',
        );
        $this->assertSame(0, StockMovement::query()->where('reason', StockMovementReason::JobReturn)->count());
        $this->assertSame(5, $item->fresh()->part->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_decreasing_the_quantity_writes_one_job_return_of_the_difference(): void
    {
        $item = $this->partLine(quantity: 5, stock: 10);

        $this->service()->update($item, $this->actor(), $this->editPayload(2));

        $this->assertSame([3], $this->movementChanges(StockMovementReason::JobReturn));
        $this->assertSame([-5], $this->movementChanges(StockMovementReason::JobUse));
        $this->assertSame(8, $item->fresh()->part->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_saving_an_unchanged_quantity_writes_no_movement_at_all(): void
    {
        $item = $this->partLine(quantity: 3, stock: 10);
        $before = StockMovement::query()->count();

        $this->service()->update($item, $this->actor(), $this->editPayload(3, 'Reworded but same quantity'));

        $this->assertSame($before, StockMovement::query()->count());
        $this->assertSame('Reworded but same quantity', $item->fresh()->description);
        $this->assertSame(7, $item->fresh()->part->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_quantity_change_leaves_the_lines_unit_price_alone(): void
    {
        $item = $this->partLine(quantity: 2, stock: 10, attributes: ['unit_price' => '4200.00']);

        $this->assertSame('4200.00', $item->unit_price);

        $this->service()->update($item, $this->actor(), $this->editPayload(4));

        $this->assertSame('4200.00', $item->fresh()->unit_price);
    }

    public function test_deleting_a_part_line_returns_the_full_quantity_and_keeps_the_movement(): void
    {
        $item = $this->partLine(quantity: 4, stock: 10);
        $part = $item->part;

        $this->service()->delete($item, $this->actor());

        $returns = StockMovement::query()->where('reason', StockMovementReason::JobReturn)->get();

        $this->assertNull(JobItem::query()->find($item->getKey()));
        $this->assertCount(1, $returns);
        $this->assertSame(4, $returns->first()->quantity_change);
        $this->assertNull($returns->first()->job_item_id);
        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_deleting_a_labor_line_writes_no_movement(): void
    {
        $this->stockedPart(10);
        $item = JobItem::factory()->forJob(Job::factory()->inProgress()->create())->create();
        $before = StockMovement::query()->count();

        $this->service()->delete($item, $this->actor());

        $this->assertSame($before, StockMovement::query()->count());
        $this->assertStockMatchesLedger();
    }

    public function test_a_mechanics_part_line_is_assigned_to_the_mechanic(): void
    {
        $mechanic = User::factory()->mechanic()->create();

        $item = $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $this->stockedPart(10),
            $mechanic,
            ['quantity' => 1, 'user_id' => User::factory()->mechanic()->create()->getKey()],
        );

        $this->assertSame($mechanic->getKey(), $item->user_id);
    }

    public function test_front_office_may_leave_a_part_line_unassigned(): void
    {
        $item = $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $this->stockedPart(10),
            $this->actor(),
            ['quantity' => 1, 'user_id' => null],
        );

        $this->assertNull($item->user_id);
        $this->assertNull($item->fresh()->user_id);
    }

    public function test_front_office_may_override_the_snapshot_price(): void
    {
        $item = $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $this->stockedPart(10, ['unit_price' => '4200.00']),
            $this->actor(),
            ['quantity' => 1, 'unit_price' => '3000.00'],
        );

        $this->assertSame('3000.00', $item->fresh()->unit_price);
    }

    public function test_a_mechanic_cannot_override_the_snapshot_price_through_the_service(): void
    {
        $item = $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $this->stockedPart(10, ['unit_price' => '4200.00']),
            User::factory()->mechanic()->create(),
            ['quantity' => 1, 'unit_price' => '1.00'],
        );

        $this->assertSame('4200.00', $item->fresh()->unit_price);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function partLine(int $quantity, int $stock, array $attributes = []): JobItem
    {
        return $this->service()->addPart(
            Job::factory()->inProgress()->create(),
            $this->stockedPart($stock, $attributes),
            $this->actor(),
            ['quantity' => $quantity],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayload(int $quantity, string $description = 'Edited'): array
    {
        return ['quantity' => $quantity, 'description' => $description, 'user_id' => null];
    }

    /**
     * @return list<int>
     */
    private function movementChanges(StockMovementReason $reason): array
    {
        return array_map('intval', StockMovement::query()
            ->where('reason', $reason)
            ->orderBy('id')
            ->pluck('quantity_change')
            ->all());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function stockedPart(int $quantity, array $attributes = []): Part
    {
        $part = Part::factory()->create($attributes);

        app(StockService::class)->record($part, $quantity, StockMovementReason::Restock, $this->actor());

        return $part;
    }

    private function actor(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function service(): JobItemService
    {
        return app(JobItemService::class);
    }
}
