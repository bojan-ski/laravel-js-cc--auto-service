<?php

namespace Tests\Feature\Estimates;

use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EstimateSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_estimates_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'job_id', 'number', 'status', 'subtotal', 'discount_total', 'tax_rate',
            'tax_amount', 'total', 'decision_method', 'decided_at', 'decided_by',
            'decision_note', 'superseded_at', 'emailed_at', 'created_by',
            'created_at', 'updated_at',
        ], Schema::getColumnListing('estimates'));
    }

    public function test_the_estimate_lines_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'estimate_id', 'type', 'description', 'quantity', 'unit_price',
            'discount_percent', 'line_total', 'position', 'created_at', 'updated_at',
        ], Schema::getColumnListing('estimate_lines'));
    }

    public function test_column_types_match_the_contract(): void
    {
        $estimates = collect(Schema::getColumns('estimates'))->keyBy('name');
        $lines = collect(Schema::getColumns('estimate_lines'))->keyBy('name');

        foreach (['subtotal', 'discount_total', 'tax_amount', 'total'] as $money) {
            $this->assertSame('decimal(10,2)', $estimates[$money]['type'], $money);
        }

        $this->assertSame('decimal(5,2)', $estimates['tax_rate']['type']);
        $this->assertSame('varchar(20)', $estimates['status']['type']);
        $this->assertSame('varchar(20)', $estimates['decision_method']['type']);
        $this->assertSame('varchar(500)', $estimates['decision_note']['type']);
        $this->assertSame('datetime', $estimates['decided_at']['type']);
        $this->assertSame('datetime', $estimates['superseded_at']['type']);
        $this->assertSame('datetime', $estimates['emailed_at']['type']);
        $this->assertStringContainsString('0', (string) $estimates['discount_total']['default']);

        foreach (['decision_method', 'decided_at', 'decided_by', 'decision_note', 'superseded_at', 'emailed_at'] as $nullable) {
            $this->assertTrue($estimates[$nullable]['nullable'], $nullable);
        }

        $this->assertFalse($estimates['created_by']['nullable']);
        $this->assertSame('varchar(20)', $lines['type']['type']);
        $this->assertSame('decimal(8,2)', $lines['quantity']['type']);
        $this->assertSame('decimal(10,2)', $lines['unit_price']['type']);
        $this->assertSame('decimal(5,2)', $lines['discount_percent']['type']);
        $this->assertSame('decimal(10,2)', $lines['line_total']['type']);
        $this->assertStringContainsString('int', $lines['position']['type']);
        $this->assertStringContainsString('unsigned', $lines['position']['type']);
    }

    public function test_the_indexes_cover_the_number_the_status_and_every_foreign_key(): void
    {
        $estimates = collect(Schema::getIndexes('estimates'))->keyBy(fn (array $index): string => implode(',', $index['columns']));
        $lines = collect(Schema::getIndexes('estimate_lines'))->keyBy(fn (array $index): string => implode(',', $index['columns']));

        $this->assertTrue($estimates['number']['unique']);

        foreach (['job_id', 'status', 'decision_method', 'decided_by', 'created_by'] as $column) {
            $this->assertArrayHasKey($column, $estimates->all(), $column);
        }

        $this->assertArrayHasKey('estimate_id', $lines->all());
        $this->assertArrayHasKey('type', $lines->all());
    }

    public function test_the_foreign_keys_restrict_every_parent_but_cascade_lines_from_their_estimate(): void
    {
        $estimates = collect(Schema::getForeignKeys('estimates'))->keyBy(fn (array $key): string => implode(',', $key['columns']));
        $lines = collect(Schema::getForeignKeys('estimate_lines'))->keyBy(fn (array $key): string => implode(',', $key['columns']));

        $this->assertSame('jobs', $estimates['job_id']['foreign_table']);
        $this->assertSame('users', $estimates['decided_by']['foreign_table']);
        $this->assertSame('users', $estimates['created_by']['foreign_table']);

        foreach (['job_id', 'decided_by', 'created_by'] as $column) {
            $this->assertSame('restrict', strtolower($estimates[$column]['on_delete']), $column);
        }

        $this->assertSame('estimates', $lines['estimate_id']['foreign_table']);
        $this->assertSame('cascade', strtolower($lines['estimate_id']['on_delete']));
    }

    public function test_a_number_is_never_reused(): void
    {
        Estimate::factory()->create(['number' => 'EST-2026-0001']);

        $this->expectException(QueryException::class);

        Estimate::factory()->create(['number' => 'EST-2026-0001']);
    }

    public function test_a_job_with_an_estimate_cannot_be_removed_outright(): void
    {
        $estimate = Estimate::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('jobs')->where('id', $estimate->job_id)->delete();
    }

    public function test_the_creator_of_an_estimate_cannot_be_removed_outright(): void
    {
        $creator = User::factory()->receptionist()->create();
        Estimate::factory()->create(['created_by' => $creator->getKey()]);

        $this->expectException(QueryException::class);

        $creator->forceDelete();
    }

    public function test_removing_an_estimate_row_outright_takes_its_lines_with_it(): void
    {
        $job = Job::factory()->inProgress()->create();
        $estimate = Estimate::factory()->forJob($job)->create();
        EstimateLine::factory()->for($estimate)->create();

        DB::table('estimates')->where('id', $estimate->getKey())->delete();

        $this->assertSame(0, EstimateLine::query()->count());
    }
}
