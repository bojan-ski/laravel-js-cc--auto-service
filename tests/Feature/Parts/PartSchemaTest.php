<?php

namespace Tests\Feature\Parts;

use App\Models\JobItem;
use App\Models\Part;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'sku', 'name', 'brand_id', 'category', 'description', 'unit_price', 'cost_price',
            'quantity_in_stock', 'reorder_level', 'location',
            'deleted_at', 'created_at', 'updated_at',
        ], Schema::getColumnListing('parts'));
    }

    public function test_nullability_matches_the_contract(): void
    {
        $nullable = collect(Schema::getColumns('parts'))->mapWithKeys(
            fn (array $column): array => [$column['name'] => $column['nullable']]
        );

        foreach (['brand_id', 'category', 'description', 'cost_price', 'location', 'deleted_at'] as $column) {
            $this->assertTrue($nullable[$column], "{$column} should be nullable");
        }

        foreach (['sku', 'name', 'unit_price', 'quantity_in_stock', 'reorder_level'] as $column) {
            $this->assertFalse($nullable[$column], "{$column} should not be nullable");
        }
    }

    public function test_the_sku_is_unique_across_trashed_rows_too(): void
    {
        $this->assertTrue(
            collect(Schema::getIndexes('parts'))
                ->contains(fn (array $index): bool => $index['columns'] === ['sku'] && $index['unique']),
            'Expected a unique index on sku.'
        );

        Part::factory()->deleted()->create(['sku' => 'OF-1042']);

        $this->expectException(QueryException::class);

        Part::factory()->create(['sku' => 'OF-1042']);
    }

    public function test_the_searched_columns_are_indexed(): void
    {
        $indexes = collect(Schema::getIndexes('parts'));

        foreach (['sku', 'name'] as $column) {
            $this->assertTrue(
                $indexes->contains(fn (array $index): bool => $index['columns'] === [$column]),
                "Expected an index on {$column}."
            );
        }
    }

    public function test_stock_defaults_to_zero(): void
    {
        $part = Part::factory()->create();

        $this->assertSame(0, $part->fresh()->quantity_in_stock);
    }

    public function test_stock_accepts_and_stores_a_negative_value(): void
    {
        $part = Part::factory()->create(['quantity_in_stock' => -5]);

        $this->assertSame(-5, $part->fresh()->quantity_in_stock);
    }

    public function test_prices_round_trip_at_their_declared_precision(): void
    {
        $part = Part::factory()->create([
            'unit_price' => '99999999.99',
            'cost_price' => '3000.05',
        ]);

        $fresh = $part->fresh();

        $this->assertSame('99999999.99', $fresh->unit_price);
        $this->assertSame('3000.05', $fresh->cost_price);
    }

    public function test_the_job_items_foreign_key_restricts_deleting_a_fitted_part(): void
    {
        $part = Part::factory()->create();
        JobItem::factory()->create(['part_id' => $part->getKey()]);

        $this->expectException(QueryException::class);

        $part->forceDelete();
    }

    public function test_soft_deleting_a_fitted_part_is_not_blocked_by_the_foreign_key(): void
    {
        $part = Part::factory()->create();
        JobItem::factory()->create(['part_id' => $part->getKey()]);

        $part->delete();

        $this->assertSoftDeleted($part);
    }
}
