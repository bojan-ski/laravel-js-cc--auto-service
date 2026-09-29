<?php

namespace Tests\Feature\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * DDL commits implicitly on MySQL, so this class cannot share the
 * LazilyRefreshDatabase transaction. DatabaseMigrations migrates fresh before
 * each test, rolls everything back afterwards and resets the refresh state, so
 * the next class in the process re-migrates from scratch.
 */
class InvoiceBuyerBackfillTest extends TestCase
{
    use DatabaseMigrations;

    private const INVOICE_COLUMNS_BEFORE = [
        'id', 'job_id', 'number', 'status', 'subtotal', 'tax_rate', 'tax_amount',
        'total', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'voided_by',
        'issued_by', 'created_at', 'updated_at',
    ];

    private const CUSTOMER_COLUMNS_BEFORE = [
        'id', 'name', 'phone', 'email', 'address', 'notes', 'deleted_at', 'created_at', 'updated_at',
    ];

    private function migration(string $name): Migration
    {
        $paths = glob(database_path("migrations/*_{$name}.php"));
        $this->assertCount(1, $paths, "Expected exactly one {$name} migration.");

        return require $paths[0];
    }

    /**
     * Later migrations that alter the same tables are rolled back first, as
     * `migrate:rollback` would, so this spec's down() runs against the table
     * shape it was written for.
     */
    private function rollBackThisSpec(): void
    {
        $this->migration('alter_invoices_table_add_payment_method')->down();
        $this->migration('alter_invoices_table_add_buyer_snapshot')->down();
        $this->migration('alter_customers_table_add_tax_id_and_registration_number')->down();
    }

    private function migrateThisSpec(): void
    {
        $this->migration('alter_customers_table_add_tax_id_and_registration_number')->up();
        $this->migration('alter_invoices_table_add_buyer_snapshot')->up();
        $this->migration('alter_invoices_table_add_payment_method')->up();
    }

    private function insertInvoice(Job $job, string $status, User $issuer, ?User $voider = null): int
    {
        static $sequence = 0;
        $sequence++;

        return DB::table('invoices')->insertGetId([
            'job_id' => $job->getKey(),
            'number' => sprintf('INV-2026-%04d', $sequence),
            'status' => $status,
            'subtotal' => '1000.00',
            'tax_rate' => '20.00',
            'tax_amount' => '200.00',
            'total' => '1200.00',
            'issued_at' => '2026-09-01 10:00:00',
            'due_at' => null,
            'paid_at' => $status === 'paid' ? '2026-09-02 10:00:00' : null,
            'voided_at' => $status === 'void' ? '2026-09-03 10:00:00' : null,
            'voided_by' => $voider?->getKey(),
            'issued_by' => $issuer->getKey(),
            'created_at' => '2026-09-01 10:00:00',
            'updated_at' => '2026-09-03 10:00:00',
        ]);
    }

    public function test_every_existing_invoice_is_backfilled_from_its_jobs_customer(): void
    {
        $this->rollBackThisSpec();

        $issuer = User::factory()->receptionist()->create();
        $admin = User::factory()->admin()->create();
        $invoices = [];

        foreach (['unpaid', 'paid', 'void', 'trashed'] as $case) {
            $customer = Customer::factory()->create(['name' => "Customer {$case}", 'address' => "Street {$case}"]);
            $job = Job::factory()->locked()->for($customer)->create();

            $invoices[$case] = $this->insertInvoice($job, $case === 'trashed' ? 'unpaid' : $case, $issuer, $case === 'void' ? $admin : null);

            if ($case === 'trashed') {
                $customer->delete();
            }
        }

        $private = Customer::factory()->minimal()->create(['name' => 'No Address']);
        $invoices['no-address'] = $this->insertInvoice(Job::factory()->locked()->for($private)->create(), 'unpaid', $issuer);

        Invoice::saving(fn () => throw new RuntimeException('The backfill must not write through the model.'));

        $this->migrateThisSpec();

        foreach ($invoices as $case => $id) {
            $row = DB::table('invoices')
                ->join('jobs', 'jobs.id', '=', 'invoices.job_id')
                ->join('customers', 'customers.id', '=', 'jobs.customer_id')
                ->where('invoices.id', $id)
                ->first(['invoices.*', 'customers.name as customer_name', 'customers.address as customer_address']);

            $this->assertSame($row->customer_name, $row->buyer_name, "{$case}: buyer_name");
            $this->assertSame($row->customer_address, $row->buyer_address, "{$case}: buyer_address");
            $this->assertNull($row->buyer_tax_id, "{$case}: no customer has a PIB when the backfill runs");
            $this->assertNull($row->buyer_registration_number, "{$case}: no customer has an MB when the backfill runs");
            $this->assertSame('2026-09-03 10:00:00', $row->updated_at, "{$case}: the backfill must not touch updated_at");
        }

        $this->assertSame('Customer void', DB::table('invoices')->where('id', $invoices['void'])->value('buyer_name'));
        $this->assertSame('Customer trashed', DB::table('invoices')->where('id', $invoices['trashed'])->value('buyer_name'));
        $this->assertNull(DB::table('invoices')->where('id', $invoices['no-address'])->value('buyer_address'));
        $this->assertSame(0, DB::table('invoices')->whereNull('buyer_name')->count());
    }

    public function test_rolling_back_restores_both_tables_and_migrating_again_restores_the_new_shape(): void
    {
        $invoicesAfter = Schema::getColumns('invoices');
        $customersAfter = Schema::getColumns('customers');

        $this->rollBackThisSpec();

        $this->assertSame(self::INVOICE_COLUMNS_BEFORE, Schema::getColumnListing('invoices'));
        $this->assertSame(self::CUSTOMER_COLUMNS_BEFORE, Schema::getColumnListing('customers'));

        $this->migrateThisSpec();

        $this->assertEquals($invoicesAfter, Schema::getColumns('invoices'));
        $this->assertEquals($customersAfter, Schema::getColumns('customers'));
        $this->assertFalse(collect(Schema::getColumns('invoices'))->firstWhere('name', 'buyer_name')['nullable']);
    }
}
