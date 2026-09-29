<?php

namespace Tests\Feature\Mail;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Mail\InvoiceIssuedMail;
use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceIssuedMailSendingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function actor(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function invoiceableJob(?Customer $customer = null): Job
    {
        $customer ??= Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();

        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return $job;
    }

    public function test_issuing_an_invoice_queues_one_message_to_the_jobs_customer(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'owner@example.test']);

        $this->service()->issue($this->invoiceableJob($customer), $this->actor());

        Mail::assertQueued(InvoiceIssuedMail::class, fn (InvoiceIssuedMail $mail) => $mail->hasTo('owner@example.test'));
        Mail::assertQueuedCount(1);
    }

    public function test_the_message_is_queued_rather_than_sent(): void
    {
        Mail::fake();

        $this->service()->issue($this->invoiceableJob(), $this->actor());

        Mail::assertNotSent(InvoiceIssuedMail::class);
        Mail::assertQueuedCount(1);
    }

    public function test_the_queued_message_carries_the_invoice_that_was_issued(): void
    {
        Mail::fake();

        $invoice = $this->service()->issue($this->invoiceableJob(), $this->actor());

        Mail::assertQueued(
            InvoiceIssuedMail::class,
            fn (InvoiceIssuedMail $mail) => $mail->invoice->is($invoice)
                && $mail->money()['total'] === number_format((float) $invoice->total, 2),
        );
    }

    public function test_a_customer_with_no_address_receives_nothing_and_the_invoice_still_issues(): void
    {
        Mail::fake();

        $invoice = $this->service()->issue(
            $this->invoiceableJob(Customer::factory()->minimal()->create()),
            $this->actor(),
        );

        Mail::assertNothingOutgoing();
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->job->fresh()->locked_at);
    }

    public function test_marking_an_invoice_paid_queues_nothing(): void
    {
        $invoice = $this->service()->issue($this->invoiceableJob(), $this->actor());

        Mail::fake();

        $this->service()->markPaid($invoice, PaymentMethod::Cash);

        Mail::assertNothingOutgoing();
    }

    public function test_voiding_an_invoice_queues_nothing(): void
    {
        $invoice = $this->service()->issue($this->invoiceableJob(), $this->actor());

        Mail::fake();

        $this->service()->void($invoice, User::factory()->admin()->create());

        Mail::assertNothingOutgoing();
        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }

    public function test_issuing_does_not_also_queue_a_job_completed_message(): void
    {
        Mail::fake();

        $this->service()->issue($this->invoiceableJob(), $this->actor());

        Mail::assertNotQueued(JobCompletedMail::class);
    }
}
