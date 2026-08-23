<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_complaint_and_mileage_in_are_fillable(): void
    {
        $this->assertSame(['complaint', 'mileage_in'], (new Job)->getFillable());
    }

    public function test_mass_assignment_cannot_set_any_server_controlled_column(): void
    {
        $job = new Job([
            'complaint' => 'Squeal under braking',
            'mileage_in' => 120_000,
            'reference' => 'JOB-1999-0001',
            'appointment_id' => 42,
            'customer_id' => 42,
            'vehicle_id' => 42,
            'status' => JobStatus::Cancelled,
            'created_by' => 42,
            'started_at' => now(),
            'awaiting_parts_at' => now(),
            'completed_at' => now(),
            'cancelled_at' => now(),
            'locked_at' => now(),
        ]);

        $this->assertSame('Squeal under braking', $job->complaint);
        $this->assertSame(120_000, $job->mileage_in);
        $this->assertSame(JobStatus::Received, $job->status);

        foreach ([
            'reference', 'appointment_id', 'customer_id', 'vehicle_id', 'created_by',
            'started_at', 'awaiting_parts_at', 'completed_at', 'cancelled_at', 'locked_at',
        ] as $column) {
            $this->assertNull($job->{$column}, "{$column} must not be mass-assignable");
        }
    }

    public function test_a_new_job_is_received_before_it_is_saved(): void
    {
        $this->assertSame(JobStatus::Received, (new Job)->status);
    }

    public function test_status_and_timestamps_are_cast(): void
    {
        $job = Job::factory()->locked()->create()->fresh();

        $this->assertSame(JobStatus::Completed, $job->status);
        $this->assertInstanceOf(Carbon::class, $job->started_at);
        $this->assertInstanceOf(Carbon::class, $job->completed_at);
        $this->assertInstanceOf(Carbon::class, $job->locked_at);
        $this->assertIsInt($job->mileage_in);
    }

    /**
     * @return array<string, array{0: string, 1: JobStatus, 2: array<string, bool>}>
     */
    public static function guardProvider(): array
    {
        return [
            'received' => ['received', JobStatus::Received, [
                'canStart' => true, 'canPause' => false, 'canResume' => false,
                'canComplete' => false, 'canCancel' => true, 'canReopen' => false,
            ]],
            'in progress' => ['inProgress', JobStatus::InProgress, [
                'canStart' => false, 'canPause' => true, 'canResume' => false,
                'canComplete' => true, 'canCancel' => true, 'canReopen' => false,
            ]],
            'awaiting parts' => ['awaitingParts', JobStatus::AwaitingParts, [
                'canStart' => false, 'canPause' => false, 'canResume' => true,
                'canComplete' => false, 'canCancel' => true, 'canReopen' => false,
            ]],
            'completed' => ['completed', JobStatus::Completed, [
                'canStart' => false, 'canPause' => false, 'canResume' => false,
                'canComplete' => false, 'canCancel' => false, 'canReopen' => true,
            ]],
            'cancelled' => ['cancelled', JobStatus::Cancelled, [
                'canStart' => false, 'canPause' => false, 'canResume' => false,
                'canComplete' => false, 'canCancel' => false, 'canReopen' => false,
            ]],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('guardProvider')]
    public function test_each_guard_answers_correctly_for_an_unlocked_job(string $state, JobStatus $status, array $expected): void
    {
        $job = Job::factory()->{$state}()->create();

        $this->assertSame($status, $job->status);
        $this->assertFalse($job->isLocked());
        $this->assertSame($status->isWorking(), $job->isWorking());
        $this->assertSame($status->isTerminal(), $job->isTerminal());

        foreach ($expected as $guard => $allowed) {
            $this->assertSame($allowed, $job->{$guard}(), "{$guard}() in {$status->value}");
        }
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('guardProvider')]
    public function test_every_guard_is_false_when_the_job_is_locked(string $state, JobStatus $status, array $expected): void
    {
        $job = Job::factory()->{$state}()->create(['locked_at' => now()]);

        $this->assertTrue($job->isLocked());

        foreach (array_keys($expected) as $guard) {
            $this->assertFalse($job->{$guard}(), "{$guard}() must be false on a locked {$status->value} job");
        }
    }

    /**
     * @return array<string, array{0: string, 1: array<string, bool>}>
     */
    public static function factoryStateProvider(): array
    {
        return [
            'received' => ['received', ['started_at' => false, 'awaiting_parts_at' => false, 'completed_at' => false, 'cancelled_at' => false, 'locked_at' => false]],
            'in progress' => ['inProgress', ['started_at' => true, 'awaiting_parts_at' => false, 'completed_at' => false, 'cancelled_at' => false, 'locked_at' => false]],
            'awaiting parts' => ['awaitingParts', ['started_at' => true, 'awaiting_parts_at' => true, 'completed_at' => false, 'cancelled_at' => false, 'locked_at' => false]],
            'completed' => ['completed', ['started_at' => true, 'awaiting_parts_at' => false, 'completed_at' => true, 'cancelled_at' => false, 'locked_at' => false]],
            'cancelled' => ['cancelled', ['started_at' => false, 'awaiting_parts_at' => false, 'completed_at' => false, 'cancelled_at' => true, 'locked_at' => false]],
            'locked' => ['locked', ['started_at' => true, 'awaiting_parts_at' => false, 'completed_at' => true, 'cancelled_at' => false, 'locked_at' => true]],
        ];
    }

    /**
     * @param  array<string, bool>  $timestamps
     */
    #[DataProvider('factoryStateProvider')]
    public function test_each_factory_state_sets_only_its_own_timestamps(string $state, array $timestamps): void
    {
        $job = Job::factory()->{$state}()->create()->fresh();

        foreach ($timestamps as $column => $set) {
            $set
                ? $this->assertNotNull($job->{$column}, "{$state} should set {$column}")
                : $this->assertNull($job->{$column}, "{$state} should leave {$column} null");
        }
    }

    public function test_the_locked_factory_state_is_a_completed_job(): void
    {
        $this->assertSame(JobStatus::Completed, Job::factory()->locked()->create()->status);
    }

    public function test_the_factory_snapshots_the_vehicles_owner(): void
    {
        $job = Job::factory()->create();

        $this->assertSame($job->vehicle->customer_id, $job->customer_id);
        $this->assertNull($job->appointment_id);
    }

    public function test_the_customer_relation_resolves_a_soft_deleted_customer(): void
    {
        $job = Job::factory()->create();
        Customer::findOrFail($job->customer_id)->delete();

        $this->assertNotNull($job->fresh()->customer);
        $this->assertTrue($job->fresh()->customer->trashed());
    }

    public function test_the_vehicle_relation_resolves_a_soft_deleted_vehicle(): void
    {
        $job = Job::factory()->create();
        Vehicle::findOrFail($job->vehicle_id)->delete();

        $this->assertNotNull($job->fresh()->vehicle);
        $this->assertTrue($job->fresh()->vehicle->trashed());
    }

    public function test_the_created_by_relation_resolves_an_offboarded_user(): void
    {
        $job = Job::factory()->create();
        User::findOrFail($job->created_by)->delete();

        $this->assertNotNull($job->fresh()->createdBy);
        $this->assertTrue($job->fresh()->createdBy->trashed());
    }

    public function test_the_appointment_relation_and_its_inverse_resolve(): void
    {
        $appointment = Appointment::factory()->arrived()->create();
        $job = Job::factory()->forAppointment($appointment)->create();

        $this->assertTrue($job->appointment->is($appointment));
        $this->assertTrue($appointment->fresh()->job->is($job));
        $this->assertSame($appointment->customer_id, $job->customer_id);
        $this->assertSame($appointment->vehicle_id, $job->vehicle_id);
    }

    public function test_customers_and_vehicles_list_their_jobs(): void
    {
        $vehicle = Vehicle::factory()->create();
        $jobs = Job::factory()->count(2)->create(['vehicle_id' => $vehicle->getKey()]);
        Job::factory()->create();

        $this->assertEqualsCanonicalizing(
            $jobs->modelKeys(),
            $vehicle->fresh()->jobs->modelKeys(),
        );
        $this->assertEqualsCanonicalizing(
            $jobs->modelKeys(),
            Customer::findOrFail($vehicle->customer_id)->jobs->modelKeys(),
        );
    }

    public function test_reassigning_the_vehicle_does_not_rewrite_the_jobs_customer(): void
    {
        $job = Job::factory()->create();
        $originalCustomerId = $job->customer_id;

        $vehicle = Vehicle::findOrFail($job->vehicle_id);
        $vehicle->customer_id = Customer::factory()->create()->getKey();
        $vehicle->save();

        $this->assertSame($originalCustomerId, $job->fresh()->customer_id);
    }
}
