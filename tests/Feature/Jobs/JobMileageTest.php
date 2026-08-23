<?php

namespace Tests\Feature\Jobs;

use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobMileageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): JobService
    {
        return app(JobService::class);
    }

    private function walkIn(Vehicle $vehicle, ?int $mileageIn): Job
    {
        $attributes = ['complaint' => 'Intake'];

        if ($mileageIn !== null) {
            $attributes['mileage_in'] = $mileageIn;
        }

        return $this->service()->createWalkIn($vehicle, User::factory()->receptionist()->create(), $attributes);
    }

    public function test_a_higher_reading_updates_the_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);

        $job = $this->walkIn($vehicle, 150_000);

        $this->assertSame(150_000, $vehicle->fresh()->mileage);
        $this->assertSame(150_000, $job->mileage_in);
        $this->assertFalse($job->mileageInIsBelowVehicleMileage());
    }

    public function test_a_reading_on_a_vehicle_with_no_mileage_sets_it(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => null]);

        $job = $this->walkIn($vehicle, 42_000);

        $this->assertSame(42_000, $vehicle->fresh()->mileage);
        $this->assertFalse($job->mileageInIsBelowVehicleMileage());
    }

    public function test_a_lower_reading_leaves_the_vehicle_alone_but_is_kept_on_the_job(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);

        $job = $this->walkIn($vehicle, 99_999);

        $this->assertSame(100_000, $vehicle->fresh()->mileage);
        $this->assertSame(99_999, $job->mileage_in);
        $this->assertSame(99_999, $job->fresh()->mileage_in);
        $this->assertTrue($job->mileageInIsBelowVehicleMileage());
    }

    public function test_an_equal_reading_changes_nothing_and_is_not_a_warning(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000, 'updated_at' => now()->subDay()]);

        $job = $this->walkIn($vehicle, 100_000);

        $this->assertSame(100_000, $vehicle->fresh()->mileage);
        $this->assertTrue($vehicle->fresh()->updated_at->equalTo(now()->subDay()->startOfSecond()));
        $this->assertFalse($job->mileageInIsBelowVehicleMileage());
    }

    public function test_an_omitted_reading_changes_nothing_and_is_not_a_warning(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 100_000]);

        $job = $this->walkIn($vehicle, null);

        $this->assertSame(100_000, $vehicle->fresh()->mileage);
        $this->assertNull($job->mileage_in);
        $this->assertFalse($job->mileageInIsBelowVehicleMileage());
    }

    public function test_the_rule_also_applies_on_the_appointment_path(): void
    {
        $appointment = Appointment::factory()->create();
        $vehicle = Vehicle::findOrFail($appointment->vehicle_id);
        $vehicle->mileage = 80_000;
        $vehicle->save();

        $job = $this->service()->createFromAppointment($appointment, User::factory()->mechanic()->create(), [
            'complaint' => 'Intake',
            'mileage_in' => 81_500,
        ]);

        $this->assertSame(81_500, $vehicle->fresh()->mileage);
        $this->assertSame(81_500, $job->vehicle->mileage);
        $this->assertFalse($job->mileageInIsBelowVehicleMileage());
    }

    public function test_the_predicate_reads_the_loaded_vehicle(): void
    {
        $job = Job::factory()->create(['mileage_in' => 10]);
        $job->load('vehicle');
        $job->vehicle->mileage = 20;

        $this->assertTrue($job->mileageInIsBelowVehicleMileage());

        $job->vehicle->mileage = null;

        $this->assertFalse($job->mileageInIsBelowVehicleMileage());
    }
}
