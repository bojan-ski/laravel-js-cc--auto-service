<?php

namespace Tests\Feature\Parts;

use App\Enums\StockMovementReason;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use AssertsStockInvariant, LazilyRefreshDatabase;

    private function service(): StockService
    {
        return app(StockService::class);
    }

    private function actor(): User
    {
        return User::factory()->receptionist()->create();
    }

    public function test_stock_equals_the_sum_of_its_movements_after_a_mixed_sign_sequence(): void
    {
        $part = Part::factory()->create();
        $user = $this->actor();
        $service = $this->service();

        $service->record($part, 12, StockMovementReason::Restock, $user);
        $service->record($part, -3, StockMovementReason::ManualAdjustment, $user, note: 'count correction');
        $service->record($part, 5, StockMovementReason::Restock, $user);
        $service->record($part, -20, StockMovementReason::JobUse, $user);
        $service->record($part, 2, StockMovementReason::JobReturn, $user);

        $this->assertSame(-4, $part->fresh()->quantity_in_stock);
        $this->assertSame(5, StockMovement::where('part_id', $part->getKey())->count());
        $this->assertStockMatchesLedger();
    }

    public function test_a_part_with_no_movements_holds_exactly_zero(): void
    {
        Part::factory()->create();

        $this->assertSame(0, Part::query()->sole()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_the_caller_reads_the_new_balance_from_the_part_it_passed_in(): void
    {
        $part = Part::factory()->create();

        $this->service()->record($part, 7, StockMovementReason::Restock, $this->actor());

        $this->assertSame(7, $part->quantity_in_stock);
        $this->assertFalse($part->isDirty(), 'The refreshed balance should not leave the part dirty.');
    }

    public function test_the_movement_records_every_column_the_caller_supplied(): void
    {
        $part = Part::factory()->create();
        $user = $this->actor();

        $movement = $this->service()->record($part, -3, StockMovementReason::ManualAdjustment, $user, note: 'shelf count');

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->getKey(),
            'part_id' => $part->getKey(),
            'job_item_id' => null,
            'user_id' => $user->getKey(),
            'quantity_change' => -3,
            'reason' => StockMovementReason::ManualAdjustment->value,
            'note' => 'shelf count',
        ]);
    }

    public function test_the_part_row_is_locked_for_update_inside_the_transaction(): void
    {
        $part = Part::factory()->create();

        DB::enableQueryLog();
        $this->service()->record($part, 4, StockMovementReason::Restock, $this->actor());
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $locking = array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`') && str_contains($query, 'for update'),
        );

        $this->assertNotEmpty($locking, "No locking read of the part row was issued:\n".implode("\n", $queries));
    }

    /**
     * Both rollback directions are provoked by real database failures rather
     * than by stubbed model events, so the transaction is exercised exactly as
     * it would be in production.
     *
     * The movement insert is made to fail by pointing `user_id` at a user that
     * no longer exists, which the foreign key refuses. The part update is made
     * to fail by moving stock past the signed-int range, which MySQL's strict
     * mode refuses — and because the part is written first, that proves nothing
     * downstream of it ran.
     */
    public function test_a_failed_movement_insert_rolls_the_quantity_back(): void
    {
        $part = Part::factory()->create();
        $ghost = $this->actor();
        DB::table('users')->where('id', $ghost->getKey())->delete();

        try {
            $this->service()->record($part, 9, StockMovementReason::Restock, $ghost);
            $this->fail('Inserting a movement for a deleted user should violate the foreign key.');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(0, $part->fresh()->quantity_in_stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_failed_quantity_update_leaves_no_movement_behind(): void
    {
        $part = Part::factory()->create();
        $user = $this->actor();

        try {
            $this->service()->record($part, 2147483647, StockMovementReason::Restock, $user);
            $this->service()->record($part, 2147483647, StockMovementReason::Restock, $user);
            $this->fail('Moving stock past the signed-int range should be refused by the database.');
        } catch (QueryException) {
            // expected
        }

        $this->assertSame(2147483647, $part->fresh()->quantity_in_stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_two_movements_in_separate_transactions_both_apply(): void
    {
        $part = Part::factory()->create();
        $service = $this->service();

        $service->record($part, 10, StockMovementReason::Restock, $this->actor());
        $service->record($part, 6, StockMovementReason::Restock, $this->actor());

        $this->assertSame(16, $part->fresh()->quantity_in_stock);
        $this->assertSame(2, StockMovement::where('part_id', $part->getKey())->count());
        $this->assertStockMatchesLedger();
    }

    public function test_a_movement_taking_stock_below_zero_succeeds(): void
    {
        $part = Part::factory()->create();

        $this->service()->record($part, -5, StockMovementReason::JobUse, $this->actor());

        $this->assertSame(-5, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_a_soft_deleted_part_can_still_have_its_stock_moved(): void
    {
        $part = Part::factory()->create();
        $this->service()->record($part, 4, StockMovementReason::Restock, $this->actor());
        $part->delete();

        $this->service()->record($part, -1, StockMovementReason::JobUse, $this->actor());

        $this->assertSame(3, Part::withTrashed()->find($part->getKey())->quantity_in_stock);
    }

    public function test_the_history_returns_the_newest_movements_first_capped_at_the_limit(): void
    {
        $part = Part::factory()->create();
        $user = $this->actor();
        $service = $this->service();

        for ($i = 1; $i <= StockService::HISTORY_LIMIT + 5; $i++) {
            $service->record($part, $i, StockMovementReason::Restock, $user);
        }

        $history = $service->historyFor($part);

        $this->assertCount(StockService::HISTORY_LIMIT, $history);
        $this->assertSame(StockService::HISTORY_LIMIT + 5, $history->first()->quantity_change);
        $this->assertSame(6, $history->last()->quantity_change);
        $this->assertSame(StockService::HISTORY_LIMIT + 5, $service->movementCountFor($part));
    }

    public function test_the_history_is_scoped_to_its_own_part(): void
    {
        $part = Part::factory()->create();
        $other = Part::factory()->create();
        $user = $this->actor();

        $this->service()->record($part, 3, StockMovementReason::Restock, $user);
        $this->service()->record($other, 8, StockMovementReason::Restock, $user);

        $this->assertSame([3], $this->service()->historyFor($part)->pluck('quantity_change')->all());
        $this->assertSame(1, $this->service()->movementCountFor($part));
    }
}
