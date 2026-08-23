<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class JobCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): JobService
    {
        return app(JobService::class);
    }

    public function test_a_walk_in_job_is_created_received_and_unlocked(): void
    {
        $vehicle = Vehicle::factory()->create();
        $receptionist = User::factory()->receptionist()->create();

        $job = $this->service()->create($vehicle, $receptionist, [
            'complaint' => 'Pulls right under braking',
            'mileage_in' => 184_320,
        ]);

        $this->assertTrue($job->exists);
        $this->assertSame(JobStatus::Received, $job->status);
        $this->assertSame('Pulls right under braking', $job->complaint);
        $this->assertSame(184_320, $job->mileage_in);
        $this->assertSame($receptionist->getKey(), $job->created_by);
        $this->assertNull($job->appointment_id);
        $this->assertNull($job->started_at);
        $this->assertNull($job->awaiting_parts_at);
        $this->assertNull($job->completed_at);
        $this->assertNull($job->cancelled_at);
        $this->assertNull($job->locked_at);
        $this->assertMatchesRegularExpression('/^JOB-\d{4}-\d{4}$/', $job->reference);
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'status' => 'received']);
    }

    public function test_the_customer_and_vehicle_are_snapshotted_from_the_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $job = $this->service()->create($vehicle, User::factory()->admin()->create(), ['complaint' => 'Noise']);

        $this->assertSame($vehicle->getKey(), $job->vehicle_id);
        $this->assertSame($vehicle->customer_id, $job->customer_id);
    }

    public function test_creating_from_an_appointment_links_it(): void
    {
        $appointment = Appointment::factory()->arrived()->create();
        $vehicle = Vehicle::findOrFail($appointment->vehicle_id);

        $job = $this->service()->create($vehicle, User::factory()->receptionist()->create(), ['complaint' => 'Service'], $appointment);

        $this->assertSame($appointment->getKey(), $job->appointment_id);
        $this->assertSame($appointment->customer_id, $job->customer_id);
        $this->assertTrue($appointment->fresh()->job->is($job));
    }

    public function test_an_appointment_for_a_different_vehicle_is_refused(): void
    {
        $appointment = Appointment::factory()->arrived()->create();
        $otherVehicle = Vehicle::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->create($otherVehicle, User::factory()->receptionist()->create(), ['complaint' => 'Service'], $appointment);
    }

    public function test_mileage_in_is_optional(): void
    {
        $job = $this->service()->create(Vehicle::factory()->create(), User::factory()->receptionist()->create(), ['complaint' => 'Unknown mileage']);

        $this->assertNull($job->mileage_in);
    }

    public function test_server_controlled_attributes_in_the_input_are_ignored(): void
    {
        $vehicle = Vehicle::factory()->create();
        $receptionist = User::factory()->receptionist()->create();
        $stranger = Customer::factory()->create();

        $job = $this->service()->create($vehicle, $receptionist, [
            'complaint' => 'Legit complaint',
            'status' => JobStatus::Completed->value,
            'reference' => 'JOB-1999-9999',
            'customer_id' => $stranger->getKey(),
            'created_by' => 999_999,
            'locked_at' => now(),
        ]);

        $this->assertSame(JobStatus::Received, $job->status);
        $this->assertNotSame('JOB-1999-9999', $job->reference);
        $this->assertSame($vehicle->customer_id, $job->customer_id);
        $this->assertSame($receptionist->getKey(), $job->created_by);
        $this->assertNull($job->locked_at);
    }

    public function test_the_vehicles_mileage_is_not_touched_here(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);

        $this->service()->create($vehicle, User::factory()->receptionist()->create(), [
            'complaint' => 'Intake',
            'mileage_in' => 150_000,
        ]);

        $this->assertSame(100_000, $vehicle->fresh()->mileage);
    }

    public function test_a_job_can_be_opened_for_a_vehicle_whose_owner_is_soft_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();
        Customer::findOrFail($vehicle->customer_id)->delete();

        $job = $this->service()->create($vehicle, User::factory()->receptionist()->create(), ['complaint' => 'Orphan owner']);

        $this->assertSame($vehicle->customer_id, $job->customer_id);
        $this->assertTrue(Job::query()->whereKey($job->getKey())->exists());
    }
}
