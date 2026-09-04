<?php

namespace Tests\Feature\Parts;

use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_table_has_exactly_the_specified_columns_and_no_soft_delete(): void
    {
        $columns = Schema::getColumnListing('stock_movements');

        $this->assertEqualsCanonicalizing([
            'id', 'part_id', 'job_item_id', 'user_id', 'supplier_id',
            'quantity_change', 'reason', 'note',
            'created_at', 'updated_at',
        ], $columns);

        $this->assertNotContains('deleted_at', $columns, 'The ledger is append-only and must not be soft-deletable.');
    }

    public function test_nullability_matches_the_contract(): void
    {
        $nullable = collect(Schema::getColumns('stock_movements'))->mapWithKeys(
            fn (array $column): array => [$column['name'] => $column['nullable']]
        );

        foreach (['job_item_id', 'supplier_id', 'note'] as $column) {
            $this->assertTrue($nullable[$column], "{$column} should be nullable");
        }

        foreach (['part_id', 'user_id', 'quantity_change', 'reason'] as $column) {
            $this->assertFalse($nullable[$column], "{$column} should not be nullable");
        }
    }

    public function test_the_reason_is_indexed(): void
    {
        $this->assertTrue(
            collect(Schema::getIndexes('stock_movements'))
                ->contains(fn (array $index): bool => $index['columns'] === ['reason']),
            'Expected an index on reason.'
        );
    }

    public function test_quantity_change_stores_a_negative_value(): void
    {
        $movement = StockMovement::factory()->jobUse(4)->create();

        $this->assertSame(-4, $movement->fresh()->quantity_change);
    }

    public function test_deleting_a_part_is_restricted_while_a_movement_references_it(): void
    {
        $part = Part::factory()->create();
        StockMovement::factory()->forPart($part)->create();

        $this->expectException(QueryException::class);

        $part->forceDelete();
    }

    public function test_soft_deleting_a_part_leaves_its_movements_intact(): void
    {
        $part = Part::factory()->create();
        $movement = StockMovement::factory()->forPart($part)->create();

        $part->delete();

        $this->assertSoftDeleted($part);
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->getKey()]);
    }

    public function test_deleting_the_user_is_restricted_while_a_movement_references_them(): void
    {
        $user = User::factory()->receptionist()->create();
        StockMovement::factory()->causedBy($user)->create();

        $this->expectException(QueryException::class);

        $user->forceDelete();
    }

    public function test_a_movement_outlives_the_line_item_that_caused_it(): void
    {
        $item = JobItem::factory()->create();
        $movement = StockMovement::factory()->jobUse()->forJobItem($item)->create();

        $this->assertSame($item->getKey(), $movement->fresh()->job_item_id);

        DB::table('job_items')->where('id', $item->getKey())->delete();

        $this->assertDatabaseHas('stock_movements', [
            'id' => $movement->getKey(),
            'job_item_id' => null,
        ]);
    }
}
