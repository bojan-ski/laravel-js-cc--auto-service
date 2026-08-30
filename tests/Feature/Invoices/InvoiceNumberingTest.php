<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoiceService;
use App\Services\JobReferenceGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function issue(): Invoice
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    public function test_the_first_invoice_of_the_year_is_numbered_one(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $this->assertSame('INV-2026-0001', $this->issue()->number);
    }

    public function test_the_sequence_increments(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $this->assertSame('INV-2026-0001', $this->issue()->number);
        $this->assertSame('INV-2026-0002', $this->issue()->number);
        $this->assertSame('INV-2026-0003', $this->issue()->number);
    }

    public function test_a_new_year_restarts_at_one(): void
    {
        $this->travelTo('2026-12-31 23:00:00');
        $this->assertSame('INV-2026-0001', $this->issue()->number);

        $this->travelTo('2027-01-01 08:00:00');
        $this->assertSame('INV-2027-0001', $this->issue()->number);
    }

    public function test_the_generator_ignores_other_years_when_finding_the_last_number(): void
    {
        Invoice::factory()->create(['number' => 'INV-2025-0400']);

        $this->assertSame(
            'INV-2026-0001',
            app(InvoiceNumberGenerator::class)->generate(now()->setYear(2026)),
        );
    }

    public function test_gaps_are_never_back_filled(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        Invoice::factory()->void()->create(['number' => 'INV-2026-0005']);

        $this->assertSame('INV-2026-0006', $this->issue()->number);
    }

    public function test_the_job_and_invoice_sequences_are_independent(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        Job::factory()->count(6)->create();

        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        $nextJobReference = app(JobReferenceGenerator::class)->generate(now());
        $this->assertStringStartsWith('JOB-2026-', $nextJobReference);

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        $this->assertSame('INV-2026-0001', $invoice->number, 'Invoices number from their own table.');
        $this->assertSame(
            $nextJobReference,
            app(JobReferenceGenerator::class)->generate(now()),
            'Issuing an invoice must not consume a job reference.',
        );
    }
}
