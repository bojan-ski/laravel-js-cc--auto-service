<?php

namespace Tests\Feature\Mail;

use App\Mail\InvoiceIssuedMail;
use App\Mail\JobCompletedMail;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AppointmentService;
use App\Services\InvoiceService;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NothingElseSendsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_appointment_transition_queues_nothing(): void
    {
        Mail::fake();

        $service = app(AppointmentService::class);
        $booker = User::factory()->receptionist()->create();
        $vehicle = Vehicle::factory()->create();

        $service->create($vehicle, $booker, ['scheduled_at' => now()->addDay(), 'duration_minutes' => 60]);

        $service->cancel(Appointment::factory()->booked()->create());
        $service->markNoShow(Appointment::factory()->booked()->create());
        $service->markArrived(Appointment::factory()->booked()->create());
        $service->reschedule(Appointment::factory()->booked()->create(), [
            'scheduled_at' => now()->addDays(2),
            'duration_minutes' => 90,
        ]);

        Mail::assertNothingOutgoing();
    }

    public function test_converting_an_arrived_appointment_into_a_job_queues_nothing(): void
    {
        Mail::fake();

        $appointment = Appointment::factory()->booked()->create();

        app(JobService::class)->createFromAppointment(
            $appointment,
            User::factory()->receptionist()->create(),
            ['complaint' => 'grinding noise on left turn'],
        );

        Mail::assertNothingOutgoing();
    }

    public function test_no_message_is_ever_addressed_to_a_staff_user(): void
    {
        Mail::fake();

        $staff = User::factory()->receptionist()->create();
        $customer = Customer::factory()->create(['email' => 'owner@example.test']);
        $vehicle = Vehicle::factory()->for($customer)->create();

        $job = Job::factory()->inProgress()->for($customer)->for($vehicle)->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        app(JobService::class)->complete($job);
        app(InvoiceService::class)->issue($job->fresh(), $staff);

        Mail::assertOutgoingCount(2);

        $queued = Mail::queued(JobCompletedMail::class)
            ->merge(Mail::queued(InvoiceIssuedMail::class));

        $this->assertCount(2, $queued);

        foreach ($queued as $mail) {
            $this->assertTrue($mail->hasTo('owner@example.test'));
            $this->assertFalse($mail->hasTo($staff->email));
        }
    }
}
