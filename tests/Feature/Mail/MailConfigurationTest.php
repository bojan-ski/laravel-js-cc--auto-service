<?php

namespace Tests\Feature\Mail;

use App\Mail\CustomerMailable;
use App\Mail\EstimateMail;
use App\Mail\InvoiceIssuedMail;
use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\EstimateService;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function mailables(): array
    {
        return [
            'job completed' => [JobCompletedMail::class],
            'invoice issued' => [InvoiceIssuedMail::class],
            'estimate' => [EstimateMail::class],
        ];
    }

    /**
     * @param  class-string<CustomerMailable>  $mailable
     */
    private function mailableFor(string $mailable): CustomerMailable
    {
        return match ($mailable) {
            JobCompletedMail::class => $this->jobCompletedMail(),
            InvoiceIssuedMail::class => $this->invoiceIssuedMail(),
            EstimateMail::class => $this->estimateMail(),
        };
    }

    private function estimateMail(): EstimateMail
    {
        $customer = Customer::factory()->create(['email' => 'owner@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->inProgress()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $estimate = app(EstimateService::class)->create($job, User::factory()->receptionist()->create());

        return new EstimateMail($estimate, $customer, $vehicle);
    }

    private function jobCompletedMail(): JobCompletedMail
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        return new JobCompletedMail($job, $customer, $vehicle);
    }

    private function invoiceIssuedMail(): InvoiceIssuedMail
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        return new InvoiceIssuedMail($invoice, $customer, $vehicle);
    }

    /**
     * @param  class-string  $mailable
     */
    #[DataProvider('mailables')]
    public function test_it_is_queued_and_serializes_models(string $mailable): void
    {
        $this->assertTrue(is_subclass_of($mailable, ShouldQueue::class));
        $this->assertContains(SerializesModels::class, class_uses_recursive($mailable));
    }

    /**
     * @param  class-string  $mailable
     */
    #[DataProvider('mailables')]
    public function test_it_defers_the_send_until_the_transaction_commits(string $mailable): void
    {
        $this->assertTrue(property_exists($mailable, 'afterCommit'));

        $property = (new ReflectionClass($mailable))->getProperty('afterCommit');

        $this->assertTrue($property->isPublic());
        $this->assertTrue($property->getDefaultValue());
    }

    /**
     * @param  class-string  $mailable
     */
    #[DataProvider('mailables')]
    public function test_the_queue_worker_resolves_the_retry_timeout_backoff_and_after_commit_values(string $mailable): void
    {
        $job = new SendQueuedMailable($this->mailableFor($mailable));

        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame(60, $job->backoff());
        $this->assertTrue($job->afterCommit);
    }

    /**
     * @param  class-string<CustomerMailable>  $mailable
     */
    #[DataProvider('mailables')]
    public function test_a_failed_send_logs_the_concrete_mailable_and_its_record(string $mailable): void
    {
        $instance = $this->mailableFor($mailable);
        $record = match ($mailable) {
            JobCompletedMail::class => $instance->job->reference,
            InvoiceIssuedMail::class => $instance->invoice->number,
            EstimateMail::class => $instance->estimate->number,
        };

        Log::spy();

        $instance->failed(new RuntimeException('transport refused'));

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context) => $message === 'Customer email failed to send.'
                && $context['mailable'] === $mailable
                && $context['recipient'] === $instance->customer->email
                && $context['record'] === $record
                && $context['reason'] === 'transport refused',
        );
    }

    /**
     * `method_exists($mailable, 'attachments')` alone is not enough: the base
     * Mailable declares no attachments() method, so its absence only rules out
     * the Attachable-returning form. An imperative $this->attach(...) in the
     * constructor would ship a real file with that check still green, so the
     * three attachment collections are asserted empty after a build.
     *
     * @param  class-string  $mailable
     */
    #[DataProvider('mailables')]
    public function test_it_carries_no_attachments(string $mailable): void
    {
        $this->assertFalse(method_exists($mailable, 'attachments'));

        $instance = $this->mailableFor($mailable);

        $instance->render();

        $this->assertSame([], $instance->attachments);
        $this->assertSame([], $instance->rawAttachments);
        $this->assertSame([], $instance->diskAttachments);
    }

    public function test_a_failed_job_completed_send_logs_the_recipient_record_and_reason(): void
    {
        Log::spy();

        $customer = Customer::factory()->create(['email' => 'owner@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        (new JobCompletedMail($job, $customer, $vehicle))->failed(new RuntimeException('transport refused'));

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context) => $context['recipient'] === 'owner@example.test'
                && $context['record'] === $job->reference
                && $context['reason'] === 'transport refused',
        );
    }

    public function test_a_failed_invoice_issued_send_logs_the_recipient_record_and_reason(): void
    {
        $customer = Customer::factory()->create(['email' => 'owner@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        Log::spy();

        (new InvoiceIssuedMail($invoice, $customer, $vehicle))->failed(new RuntimeException('transport refused'));

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context) => $context['recipient'] === 'owner@example.test'
                && $context['record'] === $invoice->number
                && $context['reason'] === 'transport refused',
        );
    }

    public function test_a_failed_send_writes_nothing_to_the_record(): void
    {
        Log::spy();

        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        $before = $job->fresh()->getAttributes();

        (new JobCompletedMail($job, $customer, $vehicle))->failed(new RuntimeException('transport refused'));

        $this->assertSame($before, $job->fresh()->getAttributes());
        $this->assertSame(0, Invoice::query()->count());
    }
}
