<?php

namespace Tests\Feature\Mail;

use App\Enums\JobStatus;
use App\Exceptions\InvalidJobTransition;
use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class JobCompletedMailSendingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): JobService
    {
        return app(JobService::class);
    }

    private function workableJob(?Customer $customer = null): Job
    {
        $customer ??= Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();

        $job = Job::factory()->inProgress()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return $job;
    }

    public function test_completing_a_job_queues_one_message_to_the_jobs_customer(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'owner@example.test']);

        $this->service()->complete($this->workableJob($customer));

        Mail::assertQueued(JobCompletedMail::class, fn (JobCompletedMail $mail) => $mail->hasTo('owner@example.test'));
        Mail::assertQueuedCount(1);
    }

    public function test_the_message_is_queued_rather_than_sent(): void
    {
        Mail::fake();

        $this->service()->complete($this->workableJob());

        Mail::assertNotSent(JobCompletedMail::class);
        Mail::assertQueuedCount(1);
    }

    public function test_a_customer_with_no_address_receives_nothing_and_the_job_still_completes(): void
    {
        Mail::fake();

        $job = $this->workableJob(Customer::factory()->minimal()->create());

        $this->service()->complete($job);

        Mail::assertNothingOutgoing();
        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_reopening_and_completing_again_queues_a_second_message(): void
    {
        Mail::fake();

        $job = $this->workableJob();

        $this->service()->complete($job);
        $this->service()->reopen($job);
        $this->service()->complete($job);

        Mail::assertQueuedCount(2);
    }

    public function test_a_refused_second_completion_queues_nothing_further(): void
    {
        Mail::fake();

        $job = $this->workableJob();

        $this->service()->complete($job);

        Mail::assertQueuedCount(1);

        try {
            $this->service()->complete($job->fresh());
        } catch (InvalidJobTransition) {
            // The refusal is the subject of the test.
        }

        Mail::assertQueuedCount(1);
    }

    public function test_no_other_job_transition_queues_anything(): void
    {
        Mail::fake();

        $job = Job::factory()->received()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $this->service()->start($job);
        $this->service()->pause($job);
        $this->service()->resume($job);
        $this->service()->cancel($job, User::factory()->receptionist()->create());

        Mail::assertNothingOutgoing();
    }

    public function test_creating_a_job_queues_nothing(): void
    {
        Mail::fake();

        $vehicle = Vehicle::factory()->create();

        $this->service()->createWalkIn($vehicle, User::factory()->receptionist()->create(), [
            'complaint' => 'pulls right under braking',
        ]);

        Mail::assertNothingOutgoing();
    }
}
