<?php

namespace Tests\Feature\Mail;

use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Vehicle;
use App\Services\CustomerMailer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerMailerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function mailableFor(Customer $customer): JobCompletedMail
    {
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->completed()->for($customer)->for($vehicle)->create();

        return new JobCompletedMail($job, $customer, $vehicle);
    }

    public function test_a_customer_with_an_address_is_queued_a_message(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'owner@example.test']);

        app(CustomerMailer::class)->send($customer, $this->mailableFor($customer));

        Mail::assertQueued(JobCompletedMail::class, fn (JobCompletedMail $mail) => $mail->hasTo('owner@example.test'));
        Mail::assertQueuedCount(1);
    }

    public function test_a_customer_with_no_address_is_queued_nothing(): void
    {
        Mail::fake();

        $customer = Customer::factory()->minimal()->create();

        $this->assertNull($customer->email);

        app(CustomerMailer::class)->send($customer, $this->mailableFor($customer));

        Mail::assertNothingOutgoing();
    }

    public function test_an_empty_address_is_treated_as_absent(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => '']);

        app(CustomerMailer::class)->send($customer, $this->mailableFor($customer));

        Mail::assertNothingOutgoing();
    }

    public function test_a_skipped_message_logs_nothing(): void
    {
        Mail::fake();
        Log::spy();

        $customer = Customer::factory()->minimal()->create();

        app(CustomerMailer::class)->send($customer, $this->mailableFor($customer));

        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }
}
