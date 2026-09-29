<?php

namespace Tests\Feature\JobItems;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobItemSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'job_id', 'type', 'part_id', 'user_id', 'description',
            'quantity', 'unit_price', 'discount_percent', 'created_at', 'updated_at',
        ], Schema::getColumnListing('job_items'));
    }

    public function test_the_discount_percent_follows_the_unit_price_as_a_zero_default_decimal(): void
    {
        $listing = Schema::getColumnListing('job_items');

        $this->assertSame('discount_percent', $listing[array_search('unit_price', $listing, true) + 1]);

        $column = collect(Schema::getColumns('job_items'))->firstWhere('name', 'discount_percent');

        $this->assertSame('decimal(5,2)', $column['type']);
        $this->assertFalse($column['nullable']);
        $this->assertSame('0.00', $column['default']);
    }

    public function test_line_items_are_hard_deleted(): void
    {
        $this->assertFalse(Schema::hasColumn('job_items', 'deleted_at'));
    }

    public function test_nullability_matches_the_contract(): void
    {
        $nullable = collect(Schema::getColumns('job_items'))->mapWithKeys(
            fn (array $column): array => [$column['name'] => $column['nullable']]
        );

        foreach (['part_id', 'user_id'] as $column) {
            $this->assertTrue($nullable[$column], "{$column} should be nullable");
        }

        foreach (['job_id', 'type', 'description', 'quantity', 'unit_price', 'discount_percent'] as $column) {
            $this->assertFalse($nullable[$column], "{$column} should not be nullable");
        }
    }

    public function test_the_lookup_columns_are_indexed(): void
    {
        $indexes = collect(Schema::getIndexes('job_items'));

        foreach (['job_id', 'type', 'user_id'] as $column) {
            $this->assertTrue(
                $indexes->contains(fn (array $index): bool => $index['columns'] === [$column]),
                "Expected an index on {$column}."
            );
        }
    }

    public function test_part_id_gained_its_deferred_foreign_key_once_parts_existed(): void
    {
        $this->assertTrue(Schema::hasTable('parts'));

        $this->assertTrue(
            collect(Schema::getForeignKeys('job_items'))
                ->contains(fn (array $key): bool => $key['columns'] === ['part_id']
                    && $key['foreign_table'] === 'parts'),
            'part_id must reference parts now that spec 14 has created the table.'
        );
    }

    public function test_deleting_a_job_cascades_to_its_line_items(): void
    {
        $job = Job::factory()->create();
        JobItem::factory()->forJob($job)->count(2)->create();

        $job->delete();

        $this->assertSame(0, JobItem::query()->where('job_id', $job->getKey())->count());
    }

    public function test_deleting_the_named_user_is_restricted(): void
    {
        $item = JobItem::factory()->create();

        $this->expectException(QueryException::class);

        User::withTrashed()->findOrFail($item->user_id)->forceDelete();
    }

    public function test_quantity_and_price_round_trip_at_their_declared_precision(): void
    {
        $item = JobItem::factory()->create([
            'quantity' => '999999.99',
            'unit_price' => '99999999.99',
        ]);

        $fresh = $item->fresh();

        $this->assertSame('999999.99', $fresh->quantity);
        $this->assertSame('99999999.99', $fresh->unit_price);
    }

    public function test_two_decimal_places_survive_the_round_trip(): void
    {
        $item = JobItem::factory()->create(['quantity' => '1.25', 'unit_price' => '3000.05']);

        $fresh = $item->fresh();

        $this->assertSame('1.25', $fresh->quantity);
        $this->assertSame('3000.05', $fresh->unit_price);
    }

    public function test_a_description_is_required_at_the_database_level(): void
    {
        $this->expectException(QueryException::class);

        JobItem::factory()->create(['description' => null]);
    }
}
