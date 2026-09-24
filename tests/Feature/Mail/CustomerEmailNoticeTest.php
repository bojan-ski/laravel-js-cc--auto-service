<?php

namespace Tests\Feature\Mail;

use App\Mail\InvoiceIssuedMail;
use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerEmailNoticeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function jobFor(?string $email, string $state): Job
    {
        $customer = Customer::factory()->create(['email' => $email]);
        $vehicle = Vehicle::factory()->for($customer)->create();

        $job = Job::factory()->{$state}()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => '1000.00']);

        return $job;
    }

    public function test_completing_a_job_says_who_is_being_emailed(): void
    {
        Mail::fake();
        $job = $this->jobFor('maja@example.com', 'inProgress');

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.complete', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.flash.completed', ['reference' => $job->reference])
                .' '.__('mail.notice.queued', ['email' => 'maja@example.com']));

        Mail::assertQueued(JobCompletedMail::class, fn (JobCompletedMail $mail) => $mail->hasTo('maja@example.com'));
    }

    public function test_completing_a_job_for_a_customer_without_email_says_none_was_sent(): void
    {
        Mail::fake();
        $job = $this->jobFor(null, 'inProgress');

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.complete', $job))
            ->assertSessionHas('success', __('jobs.flash.completed', ['reference' => $job->reference])
                .' '.__('mail.notice.skipped'));

        Mail::assertNothingQueued();
    }

    public function test_issuing_an_invoice_says_who_is_being_emailed(): void
    {
        Mail::fake();
        $job = $this->jobFor('maja@example.com', 'completed');

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job));

        $number = $job->invoices()->sole()->number;

        $response->assertSessionHas('success', __('invoices.flash.issued', ['number' => $number])
            .' '.__('mail.notice.queued', ['email' => 'maja@example.com']));

        Mail::assertQueued(InvoiceIssuedMail::class, fn (InvoiceIssuedMail $mail) => $mail->hasTo('maja@example.com'));
    }

    public function test_issuing_an_invoice_for_a_customer_without_email_says_none_was_sent(): void
    {
        Mail::fake();
        $job = $this->jobFor(null, 'completed');

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('jobs.invoice.store', $job));

        $response->assertSessionHas('success', __('invoices.flash.issued', ['number' => $job->invoices()->sole()->number])
            .' '.__('mail.notice.skipped'));

        Mail::assertNothingQueued();
    }

    /**
     * The send resolves the customer withTrashed(), so a customer deleted
     * after the job was opened is still emailed; the notice must agree.
     */
    public function test_the_notice_matches_the_send_for_a_soft_deleted_customer(): void
    {
        Mail::fake();
        $job = $this->jobFor('maja@example.com', 'inProgress');
        Customer::query()->whereKey($job->customer_id)->sole()->delete();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.complete', $job))
            ->assertSessionHas('success', __('jobs.flash.completed', ['reference' => $job->reference])
                .' '.__('mail.notice.queued', ['email' => 'maja@example.com']));

        Mail::assertQueued(JobCompletedMail::class);
    }

    public function test_the_other_transitions_carry_no_email_notice(): void
    {
        $job = $this->jobFor('maja@example.com', 'inProgress');

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.pause', $job))
            ->assertSessionHas('success', __('jobs.flash.paused', ['reference' => $job->reference]));
    }
}
