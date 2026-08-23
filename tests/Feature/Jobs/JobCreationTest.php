<?php

namespace Tests\Feature\Jobs;

use App\Enums\AppointmentStatus;
use App\Enums\JobStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobReferenceGenerator;
use App\Services\JobService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
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

        $job = $this->service()->createWalkIn($vehicle, $receptionist, [
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

    public function test_a_walk_in_snapshots_the_customer_and_vehicle_from_the_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $job = $this->service()->createWalkIn($vehicle, User::factory()->admin()->create(), ['complaint' => 'Noise']);

        $this->assertSame($vehicle->getKey(), $job->vehicle_id);
        $this->assertSame($vehicle->customer_id, $job->customer_id);
        $this->assertTrue($job->relationLoaded('vehicle'));
        $this->assertTrue($job->vehicle->is($vehicle));
    }

    public function test_mileage_in_is_optional(): void
    {
        $job = $this->service()->createWalkIn(Vehicle::factory()->create(), User::factory()->receptionist()->create(), ['complaint' => 'Unknown mileage']);

        $this->assertNull($job->mileage_in);
    }

    public function test_server_controlled_attributes_in_the_input_are_ignored(): void
    {
        $vehicle = Vehicle::factory()->create();
        $receptionist = User::factory()->receptionist()->create();
        $stranger = Customer::factory()->create();

        $job = $this->service()->createWalkIn($vehicle, $receptionist, [
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

    public function test_a_walk_in_can_be_opened_for_a_vehicle_whose_owner_is_soft_deleted(): void
    {
        $vehicle = Vehicle::factory()->create();
        Customer::findOrFail($vehicle->customer_id)->delete();

        $job = $this->service()->createWalkIn($vehicle, User::factory()->receptionist()->create(), ['complaint' => 'Orphan owner']);

        $this->assertSame($vehicle->customer_id, $job->customer_id);
        $this->assertTrue(Job::query()->whereKey($job->getKey())->exists());
    }

    public function test_creating_from_an_appointment_marks_it_arrived_and_links_the_job(): void
    {
        $this->freezeTime();
        $appointment = Appointment::factory()->create();
        $receptionist = User::factory()->receptionist()->create();

        $job = $this->service()->createFromAppointment($appointment, $receptionist, [
            'complaint' => 'Service',
            'mileage_in' => 90_000,
        ]);

        $this->assertSame(AppointmentStatus::Arrived, $appointment->status);
        $this->assertSame(now()->toDateTimeString(), $appointment->arrived_at->toDateTimeString());
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->getKey(),
            'status' => 'arrived',
            'arrived_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame(JobStatus::Received, $job->status);
        $this->assertSame($appointment->getKey(), $job->appointment_id);
        $this->assertSame($appointment->vehicle_id, $job->vehicle_id);
        $this->assertSame($appointment->customer_id, $job->customer_id);
        $this->assertSame($receptionist->getKey(), $job->created_by);
        $this->assertSame(90_000, $job->mileage_in);
        $this->assertTrue($appointment->fresh()->job->is($job));
    }

    public function test_the_appointments_customer_wins_over_the_vehicles_current_owner(): void
    {
        $appointment = Appointment::factory()->create();
        $bookedFor = $appointment->customer_id;

        $vehicle = Vehicle::findOrFail($appointment->vehicle_id);
        $buyer = Customer::factory()->create();
        $vehicle->customer()->associate($buyer);
        $vehicle->save();

        $job = $this->service()->createFromAppointment($appointment, User::factory()->receptionist()->create(), ['complaint' => 'Service']);

        $this->assertSame($bookedFor, $job->customer_id);
        $this->assertNotSame($buyer->getKey(), $job->customer_id);
        $this->assertSame($vehicle->getKey(), $job->vehicle_id);
    }

    public function test_an_appointment_whose_vehicle_was_soft_deleted_can_still_arrive(): void
    {
        $appointment = Appointment::factory()->create();
        $vehicle = Vehicle::findOrFail($appointment->vehicle_id);
        $vehicle->mileage = null;
        $vehicle->save();
        $vehicle->delete();

        $job = $this->service()->createFromAppointment($appointment, User::factory()->mechanic()->create(), [
            'complaint' => 'Service',
            'mileage_in' => 50_000,
        ]);

        $this->assertSame(AppointmentStatus::Arrived, $appointment->fresh()->status);
        $this->assertSame($appointment->vehicle_id, $job->vehicle_id);
        $this->assertSame(50_000, Vehicle::withTrashed()->findOrFail($appointment->vehicle_id)->mileage);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function terminalProvider(): array
    {
        return [
            'arrived' => ['arrived'],
            'no-show' => ['noShow'],
            'cancelled' => ['cancelled'],
        ];
    }

    #[DataProvider('terminalProvider')]
    public function test_a_terminal_appointment_cannot_open_a_job(string $state): void
    {
        $appointment = Appointment::factory()->{$state}()->create();
        $before = $appointment->getAttributes();

        try {
            $this->service()->createFromAppointment($appointment, User::factory()->admin()->create(), ['complaint' => 'Service']);
            $this->fail('Expected InvalidAppointmentTransition.');
        } catch (InvalidAppointmentTransition $exception) {
            $this->assertSame('mark arrived', $exception->transition);
        }

        $this->assertSame(0, Job::query()->count());
        $this->assertEquals($before, $appointment->fresh()->getAttributes());
    }

    public function test_an_appointment_cannot_be_arrived_twice(): void
    {
        $appointment = Appointment::factory()->create();
        $user = User::factory()->receptionist()->create();

        $this->service()->createFromAppointment($appointment, $user, ['complaint' => 'First']);

        $this->expectException(InvalidAppointmentTransition::class);

        $this->service()->createFromAppointment($appointment->fresh(), $user, ['complaint' => 'Second']);
    }

    public function test_a_stale_instance_cannot_arrive_an_appointment_already_arrived_in_the_database(): void
    {
        $appointment = Appointment::factory()->create();
        $stale = Appointment::findOrFail($appointment->getKey());
        $user = User::factory()->receptionist()->create();

        $this->service()->createFromAppointment($appointment, $user, ['complaint' => 'First']);

        $this->assertTrue($stale->canBeMarkedArrived());

        try {
            $this->service()->createFromAppointment($stale, $user, ['complaint' => 'Second']);
            $this->fail('Expected InvalidAppointmentTransition.');
        } catch (InvalidAppointmentTransition) {
            $this->assertSame(1, Job::query()->count());
        }
    }

    public function test_a_failed_job_insert_rolls_the_arrival_back(): void
    {
        $appointment = Appointment::factory()->create();

        $this->app->instance(JobReferenceGenerator::class, new ExplodingReferenceGenerator);

        try {
            $this->service()->createFromAppointment($appointment, User::factory()->receptionist()->create(), ['complaint' => 'Service']);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $fresh = $appointment->fresh();

        $this->assertSame(AppointmentStatus::Booked, $fresh->status);
        $this->assertNull($fresh->arrived_at);
        $this->assertSame(0, Job::query()->count());
    }

    public function test_a_reference_collision_on_the_appointment_path_retries_and_arrives_exactly_once(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $taken = Job::factory()->create()->reference;
        $generator = new CollidingReferenceGenerator($taken);
        $this->app->instance(JobReferenceGenerator::class, $generator);

        $appointment = Appointment::factory()->create();

        $job = $this->service()->createFromAppointment($appointment, User::factory()->receptionist()->create(), ['complaint' => 'Service']);

        $this->assertSame(2, $generator->calls);
        $this->assertNotSame($taken, $job->reference);
        $this->assertSame(AppointmentStatus::Arrived, $appointment->status);
        $this->assertSame(AppointmentStatus::Arrived, $appointment->fresh()->status);
        $this->assertSame(1, Job::query()->where('appointment_id', $appointment->getKey())->count());
    }

    public function test_a_reference_collision_retry_still_records_the_mileage(): void
    {
        $this->travelTo('2026-03-04 09:00:00');

        $taken = Job::factory()->create()->reference;
        $generator = new CollidingReferenceGenerator($taken);
        $this->app->instance(JobReferenceGenerator::class, $generator);

        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);

        $job = $this->service()->createWalkIn($vehicle, User::factory()->receptionist()->create(), [
            'complaint' => 'Service',
            'mileage_in' => 150_000,
        ]);

        $this->assertSame(2, $generator->calls);
        $this->assertSame(150_000, $vehicle->fresh()->mileage);
        $this->assertSame(150_000, $job->vehicle->mileage);
        $this->assertFalse($job->mileageInIsBelowVehicleMileage());
    }

    public function test_a_failed_walk_in_insert_leaves_the_vehicle_mileage_untouched(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);

        $this->app->instance(JobReferenceGenerator::class, new ExplodingReferenceGenerator);

        try {
            $this->service()->createWalkIn($vehicle, User::factory()->receptionist()->create(), [
                'complaint' => 'Service',
                'mileage_in' => 150_000,
            ]);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException) {
            $this->assertSame(100_000, $vehicle->fresh()->mileage);
        }
    }
}

class ExplodingReferenceGenerator extends JobReferenceGenerator
{
    public function generate(CarbonInterface $on): string
    {
        throw new RuntimeException('boom');
    }
}

class CollidingReferenceGenerator extends JobReferenceGenerator
{
    public int $calls = 0;

    public function __construct(
        private readonly string $taken,
    ) {}

    public function generate(CarbonInterface $on): string
    {
        $this->calls++;

        return $this->calls === 1 ? $this->taken : parent::generate($on);
    }
}
