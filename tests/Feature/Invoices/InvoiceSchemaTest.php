<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoiceSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_table_has_exactly_the_specified_columns(): void
    {
        $this->assertEqualsCanonicalizing([
            'id', 'job_id', 'number', 'status',
            'buyer_name', 'buyer_address', 'buyer_tax_id', 'buyer_registration_number',
            'subtotal', 'tax_rate', 'tax_amount',
            'total', 'issued_at', 'due_at', 'paid_at', 'payment_method', 'voided_at', 'voided_by',
            'issued_by', 'created_at', 'updated_at',
        ], Schema::getColumnListing('invoices'));
    }

    public function test_the_payment_method_follows_the_paid_date_as_a_short_string(): void
    {
        $listing = Schema::getColumnListing('invoices');

        $this->assertSame('payment_method', $listing[array_search('paid_at', $listing, true) + 1]);

        $type = collect(Schema::getColumns('invoices'))->firstWhere('name', 'payment_method')['type'];

        $this->assertSame('varchar(20)', $type);
    }

    public function test_the_buyer_snapshot_columns_follow_the_status_in_order(): void
    {
        $listing = Schema::getColumnListing('invoices');
        $status = array_search('status', $listing, true);

        $this->assertSame(
            ['buyer_name', 'buyer_address', 'buyer_tax_id', 'buyer_registration_number'],
            array_slice($listing, $status + 1, 4),
        );

        $types = collect(Schema::getColumns('invoices'))->mapWithKeys(
            fn (array $column): array => [$column['name'] => $column['type']]
        );

        $this->assertSame('varchar(255)', $types['buyer_name']);
        $this->assertSame('varchar(255)', $types['buyer_address']);
        $this->assertSame('varchar(9)', $types['buyer_tax_id']);
        $this->assertSame('varchar(8)', $types['buyer_registration_number']);
    }

    public function test_invoices_are_never_soft_deleted(): void
    {
        $this->assertFalse(Schema::hasColumn('invoices', 'deleted_at'));
    }

    public function test_nullability_matches_the_contract(): void
    {
        $nullable = collect(Schema::getColumns('invoices'))->mapWithKeys(
            fn (array $column): array => [$column['name'] => $column['nullable']]
        );

        foreach (['buyer_address', 'buyer_tax_id', 'buyer_registration_number', 'due_at', 'paid_at', 'payment_method', 'voided_at', 'voided_by'] as $column) {
            $this->assertTrue($nullable[$column], "{$column} should be nullable");
        }

        foreach (['job_id', 'number', 'status', 'buyer_name', 'subtotal', 'tax_rate', 'tax_amount', 'total', 'issued_at', 'issued_by'] as $column) {
            $this->assertFalse($nullable[$column], "{$column} should not be nullable");
        }
    }

    public function test_the_number_is_unique_and_the_lookup_columns_are_indexed(): void
    {
        $indexes = collect(Schema::getIndexes('invoices'));

        $number = $indexes->first(fn (array $index): bool => $index['columns'] === ['number']);
        $this->assertNotNull($number);
        $this->assertTrue($number['unique']);

        foreach (['job_id', 'status', 'payment_method'] as $column) {
            $this->assertTrue(
                $indexes->contains(fn (array $index): bool => $index['columns'] === [$column]),
                "Expected an index on {$column}."
            );
        }
    }

    public function test_the_job_id_is_indexed_but_not_unique(): void
    {
        $jobId = collect(Schema::getIndexes('invoices'))
            ->first(fn (array $index): bool => $index['columns'] === ['job_id']);

        $this->assertNotNull($jobId);
        $this->assertFalse($jobId['unique'], 'A job may hold several invoices over its life.');
    }

    public function test_two_invoices_cannot_share_a_number(): void
    {
        $invoice = Invoice::factory()->create();

        $this->expectException(QueryException::class);

        Invoice::factory()->create(['number' => $invoice->number]);
    }

    public function test_a_job_may_hold_several_void_invoices_plus_one_that_is_not(): void
    {
        $job = Job::factory()->locked()->create();

        Invoice::factory()->void()->forJob($job)->create();
        Invoice::factory()->void()->forJob($job)->create();
        Invoice::factory()->unpaid()->forJob($job)->create();

        $this->assertSame(3, $job->invoices()->count());
        $this->assertNotNull($job->activeInvoice()->first());
    }

    public function test_deleting_the_job_is_restricted(): void
    {
        $invoice = Invoice::factory()->create();

        $this->expectException(QueryException::class);

        Job::findOrFail($invoice->job_id)->delete();
    }

    public function test_deleting_the_issuing_user_is_restricted(): void
    {
        $invoice = Invoice::factory()->create();

        $this->expectException(QueryException::class);

        User::withTrashed()->findOrFail($invoice->issued_by)->forceDelete();
    }

    public function test_deleting_the_voiding_user_is_restricted(): void
    {
        $admin = User::factory()->admin()->create();
        Invoice::factory()->void()->create(['voided_by' => $admin->getKey()]);

        $this->expectException(QueryException::class);

        $admin->forceDelete();
    }

    public function test_the_money_columns_survive_a_round_trip_as_strings(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 1234.56]);

        $invoice = app(InvoiceService::class)
            ->issue($job, User::factory()->receptionist()->create());

        $fresh = $invoice->fresh();

        $this->assertIsString($fresh->subtotal, 'bcmath needs strings, not floats.');
        $this->assertSame('1234.56', $fresh->subtotal);
        $this->assertSame('246.91', $fresh->tax_amount);
        $this->assertSame('1481.47', $fresh->total);
    }
}
