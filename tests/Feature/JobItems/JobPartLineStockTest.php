<?php

namespace Tests\Feature\JobItems;

use App\Enums\JobStatus;
use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\JobService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class JobPartLineStockTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    private const int SIGNED_INT_MAX = 2147483647;

    public function test_adding_more_than_is_in_stock_succeeds_and_warns(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(2, ['name' => 'Brake pads front']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.parts.store', $job), ['part_id' => $part->getKey(), 'quantity' => 3])
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.items.flash.part_added'))
            ->assertSessionHas('warning', __('jobs.items.flash.stock_negative', [
                'name' => 'Brake pads front',
                'balance' => -1,
            ]));

        $this->assertSame(-1, $part->fresh()->quantity_in_stock);
        $this->assertSame(1, JobItem::query()->count());
        $this->assertStockMatchesLedger();
    }

    public function test_increasing_a_quantity_past_available_stock_succeeds_and_warns(): void
    {
        $part = $this->stockedPart(4, ['name' => 'Oil filter']);
        $item = $this->addPart($part, 2);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('job-items.update', $item), ['quantity' => '6', 'description' => 'Oil filter'])
            ->assertSessionHas('warning', __('jobs.items.flash.stock_negative', [
                'name' => 'Oil filter',
                'balance' => -2,
            ]));

        $this->assertSame(-2, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_an_operation_that_leaves_stock_positive_raises_no_warning(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(10);

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.parts.store', $job), ['part_id' => $part->getKey(), 'quantity' => 3])
            ->assertSessionMissing('warning');

        $this->assertSame(7, $part->fresh()->quantity_in_stock);
    }

    public function test_a_part_already_negative_can_be_taken_further_negative(): void
    {
        $part = $this->stockedPart(1);
        $this->addPart($part, 4);

        $this->assertSame(-3, $part->fresh()->quantity_in_stock);

        $this->addPart($part, 2);

        $this->assertSame(-5, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_full_lifecycle_holds_the_invariant_at_every_step(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $pads = $this->stockedPart(10, ['name' => 'Brake pads front']);
        $fluid = $this->stockedPart(6, ['name' => 'Brake fluid 1L']);

        $this->assertStockMatchesLedger();

        $padLine = $this->items()->addPart($job, $pads, $actor, ['quantity' => 2]);
        $fluidLine = $this->items()->addPart($job, $fluid, $actor, ['quantity' => 3]);

        $this->assertSame(8, $pads->fresh()->quantity_in_stock);
        $this->assertSame(3, $fluid->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();

        $this->items()->update($padLine, $actor, ['quantity' => 5, 'description' => 'Brake pads front']);

        $this->assertSame(5, $pads->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();

        $this->items()->delete($fluidLine, $actor);

        $this->assertSame(6, $fluid->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();

        app(JobService::class)->cancel($job, $actor);

        $this->assertSame(JobStatus::Cancelled, $job->fresh()->status);
        $this->assertSame(10, $pads->fresh()->quantity_in_stock);
        $this->assertSame(6, $fluid->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();

        $this->assertSame(1, JobItem::query()->count());
        $this->assertTrue(JobItem::query()->sole()->is($padLine));
    }

    public function test_a_failed_movement_leaves_no_line_and_no_stock_change(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(10);

        try {
            $this->items()->addPart($job, $part, User::factory()->receptionist()->make(), ['quantity' => 3]);
            $this->fail('Expected the movement insert to fail.');
        } catch (QueryException) {
            //
        }

        $this->assertSame(0, JobItem::query()->count());
        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertStockMatchesLedger();
    }

    public function test_a_failed_line_insert_leaves_stock_untouched(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(10);

        try {
            $this->items()->addPart($job, $part, $this->actor(), [
                'quantity' => 3,
                'description' => str_repeat('a', 300),
            ]);
            $this->fail('Expected the line insert to fail.');
        } catch (QueryException) {
            //
        }

        $this->assertSame(0, JobItem::query()->count());
        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_failed_movement_during_an_edit_leaves_the_quantity_unchanged(): void
    {
        $part = $this->stockedPart(10);
        $item = $this->addPart($part, 2);

        try {
            $this->items()->update($item, User::factory()->receptionist()->make(), [
                'quantity' => 5,
                'description' => 'Edited',
            ]);
            $this->fail('Expected the movement insert to fail.');
        } catch (QueryException) {
            //
        }

        $this->assertSame('2.00', $item->fresh()->quantity);
        $this->assertNotSame('Edited', $item->fresh()->description);
        $this->assertSame(8, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_failure_during_a_cancellation_leaves_the_job_uncancelled_and_stock_untouched(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();

        $good = $this->stockedPart(10);
        $this->items()->addPart($job, $good, $actor, ['quantity' => 3]);

        $brimming = $this->stockedPart(self::SIGNED_INT_MAX);
        $this->items()->addPart($job, $brimming, $actor, ['quantity' => 5]);
        app(StockService::class)->record($brimming, 5, StockMovementReason::Restock, $actor);

        $movementsBefore = StockMovement::query()->count();

        try {
            app(JobService::class)->cancel($job, $actor);
            $this->fail('Expected the overflowing return to fail.');
        } catch (QueryException) {
            //
        }

        $this->assertSame(JobStatus::InProgress, $job->fresh()->status);
        $this->assertNull($job->fresh()->cancelled_at);
        $this->assertSame(7, $good->fresh()->quantity_in_stock);
        $this->assertSame(self::SIGNED_INT_MAX, $brimming->fresh()->quantity_in_stock);
        $this->assertSame($movementsBefore, StockMovement::query()->count());
        $this->assertStockMatchesLedger();
    }

    /**
     * The route resolves the line before the job row is locked, so a second
     * writer can change it in between. The delta must come from the row as it
     * stands under the lock, or the shelf drifts from what the line says
     * (spec 36 review, workflow-auditor).
     */
    public function test_an_edit_from_a_stale_line_moves_stock_by_what_the_row_holds_under_the_lock(): void
    {
        $part = $this->stockedPart(10);
        $item = $this->addPart($part, 5);
        $stale = $item->fresh();

        $this->items()->update($item->fresh(), $this->actor(), ['quantity' => '3']);
        $this->items()->update($stale, $this->actor(), ['quantity' => '4']);

        $this->assertSame('4.00', $item->fresh()->quantity);
        $this->assertSame(6, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_an_edit_back_to_the_stale_value_is_still_written(): void
    {
        $part = $this->stockedPart(10);
        $item = $this->addPart($part, 5);
        $stale = $item->fresh();

        $this->items()->update($item->fresh(), $this->actor(), ['quantity' => '3']);
        $this->items()->update($stale, $this->actor(), ['quantity' => '5']);

        $this->assertSame('5.00', $item->fresh()->quantity);
        $this->assertSame(5, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_delete_from_a_stale_line_returns_what_the_row_holds(): void
    {
        $part = $this->stockedPart(10);
        $item = $this->addPart($part, 5);
        $stale = $item->fresh();

        $this->items()->update($item->fresh(), $this->actor(), ['quantity' => '3']);
        $this->items()->delete($stale, $this->actor());

        $this->assertFalse(JobItem::query()->whereKey($item->getKey())->exists());
        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_line_deleted_elsewhere_is_not_returned_to_stock_twice(): void
    {
        $part = $this->stockedPart(10);
        $item = $this->addPart($part, 5);
        $stale = $item->fresh();

        $this->items()->delete($item->fresh(), $this->actor());
        $movementsAfterDelete = StockMovement::query()->count();

        try {
            $this->items()->delete($stale, $this->actor());
            $this->fail('A line that no longer exists must not be deleted again.');
        } catch (ModelNotFoundException) {
            // the refusal is the expected outcome
        }

        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertSame($movementsAfterDelete, StockMovement::query()->count());
        $this->assertStockMatchesLedger();
    }

    private function addPart(Part $part, int $quantity): JobItem
    {
        return $this->items()->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $this->actor(),
            ['quantity' => $quantity],
        );
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

    private function items(): JobItemService
    {
        return app(JobItemService::class);
    }
}
