<?php

namespace Tests\Feature\Invoices;

use App\Mail\InvoiceIssuedMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use App\Services\VehicleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class InvoiceBuyerSnapshotTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function invoiceableJobFor(Customer $customer, ?Vehicle $vehicle = null): Job
    {
        $vehicle ??= Vehicle::factory()->for($customer)->create();

        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 1000]);

        return $job;
    }

    private function issue(Job $job): Invoice
    {
        return $this->service()->issue($job, User::factory()->receptionist()->create());
    }

    private function assertSnapshotOf(Customer $customer, Invoice $invoice): void
    {
        $stored = Invoice::query()->findOrFail($invoice->getKey());

        $this->assertSame($customer->name, $stored->buyer_name);
        $this->assertSame($customer->address, $stored->buyer_address);
        $this->assertSame($customer->tax_id, $stored->buyer_tax_id);
        $this->assertSame($customer->registration_number, $stored->buyer_registration_number);
    }

    public function test_issuing_copies_a_company_customer_onto_the_invoice(): void
    {
        $customer = Customer::factory()->company()->create(['address' => 'Autoput za Zagreb 18']);

        $invoice = $this->issue($this->invoiceableJobFor($customer));

        $this->assertSnapshotOf($customer, $invoice);
        $this->assertTrue($invoice->hasCompanyBuyer());
    }

    public function test_issuing_copies_a_private_customer_with_null_tax_columns(): void
    {
        $customer = Customer::factory()->minimal()->create();

        $invoice = $this->issue($this->invoiceableJobFor($customer));

        $this->assertSnapshotOf($customer, $invoice);
        $this->assertNull($invoice->fresh()->buyer_address);
        $this->assertFalse($invoice->fresh()->hasCompanyBuyer());
    }

    public function test_a_soft_deleted_customer_is_still_billed_by_name(): void
    {
        $customer = Customer::factory()->company()->create();
        $job = $this->invoiceableJobFor($customer);
        $customer->delete();

        $invoice = $this->issue($job);

        $this->assertSnapshotOf($customer, $invoice);
    }

    public function test_the_buyer_is_the_jobs_customer_not_the_vehicles_current_owner(): void
    {
        $original = Customer::factory()->company()->create(['name' => 'Original Owner d.o.o.']);
        $newOwner = Customer::factory()->company()->create(['name' => 'Second Owner d.o.o.']);
        $vehicle = Vehicle::factory()->for($original)->create();
        $job = $this->invoiceableJobFor($original, $vehicle);

        app(VehicleService::class)->reassign($vehicle, $newOwner);

        $invoice = $this->issue($job);

        $this->assertSnapshotOf($original, $invoice);
        $this->assertNotSame($newOwner->tax_id, $invoice->fresh()->buyer_tax_id);
    }

    public function test_a_rolled_back_issue_leaves_no_snapshot_behind(): void
    {
        $job = $this->invoiceableJobFor(Customer::factory()->company()->create());

        Job::saving(function (Job $saving): void {
            if ($saving->isDirty('locked_at')) {
                throw new RuntimeException('lock write failed');
            }
        });

        try {
            $this->issue($job);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_void_and_reissue_keeps_each_invoices_own_snapshot(): void
    {
        $customer = Customer::factory()->company()->create([
            'name' => 'First Name d.o.o.',
            'address' => 'First Street 1',
        ]);
        $first = [
            'name' => $customer->name,
            'address' => $customer->address,
            'tax_id' => $customer->tax_id,
            'registration_number' => $customer->registration_number,
        ];
        $job = $this->invoiceableJobFor($customer);

        $original = $this->issue($job);
        $this->service()->void($original, User::factory()->admin()->create());

        $customer->update([
            'name' => 'Second Name',
            'address' => 'Second Street 2',
            'tax_id' => null,
            'registration_number' => null,
        ]);

        $reissued = $this->issue($job->fresh());

        $voided = $original->fresh();
        $this->assertSame($first['name'], $voided->buyer_name);
        $this->assertSame($first['address'], $voided->buyer_address);
        $this->assertSame($first['tax_id'], $voided->buyer_tax_id);
        $this->assertSame($first['registration_number'], $voided->buyer_registration_number);

        $this->assertSnapshotOf($customer->fresh(), $reissued);
        $this->assertFalse($reissued->fresh()->hasCompanyBuyer());
    }

    public function test_the_snapshot_is_one_customer_read_shared_with_the_email(): void
    {
        Mail::fake();
        $customer = Customer::factory()->company()->create();
        $job = $this->invoiceableJobFor($customer);

        DB::enableQueryLog();
        $this->issue($job);
        $reads = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'from `customers`'));

        $this->assertCount(1, $reads, $reads->implode("\n"));
        Mail::assertQueued(InvoiceIssuedMail::class, fn (InvoiceIssuedMail $mail): bool => $mail->customer->is($customer));
    }

    public function test_the_buyer_columns_are_not_mass_assignable(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertFalse($invoice->isFillable('buyer_name'));
        $this->assertFalse($invoice->isFillable('buyer_tax_id'));
        $this->assertSame([], $invoice->getFillable());
    }

    public function test_the_factory_snapshots_its_jobs_customer_even_when_trashed(): void
    {
        $customer = Customer::factory()->company()->create();
        $job = Job::factory()->locked()->for($customer)->create();
        $customer->delete();

        $invoice = Invoice::factory()->forJob($job)->create();

        $this->assertSnapshotOf($customer, $invoice);
    }
}
