<?php

namespace Tests\Feature\Jobs;

use App\Models\Appointment;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobIntakeValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->receptionist()->create());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function submit(array $input): TestResponse
    {
        return $this->from(route('jobs.create'))->post(route('jobs.store'), $input);
    }

    public function test_submitting_both_a_vehicle_and_an_appointment_is_rejected(): void
    {
        $appointment = Appointment::factory()->create();

        $this->submit([
            'appointment_id' => $appointment->getKey(),
            'vehicle_id' => $appointment->vehicle_id,
            'complaint' => 'Both',
        ])->assertRedirect(route('jobs.create'))->assertSessionHasErrors('appointment_id');

        $this->assertSame(0, Job::query()->count());
    }

    public function test_submitting_neither_a_vehicle_nor_an_appointment_is_rejected(): void
    {
        $this->submit(['complaint' => 'Neither'])
            ->assertSessionHasErrors('vehicle_id')
            ->assertSessionDoesntHaveErrors('appointment_id');

        $this->assertSame(0, Job::query()->count());
    }

    public function test_a_soft_deleted_vehicle_is_rejected(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();

        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => 'Deleted'])
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_an_unknown_vehicle_is_rejected(): void
    {
        $this->submit(['vehicle_id' => 999_999, 'complaint' => 'Ghost'])
            ->assertSessionHasErrors('vehicle_id');
    }

    public function test_an_unknown_appointment_is_rejected(): void
    {
        $this->submit(['appointment_id' => 999_999, 'complaint' => 'Ghost'])
            ->assertSessionHasErrors('appointment_id');

        $this->assertCount(1, session('errors')->get('appointment_id'));
    }

    public function test_the_complaint_is_required(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->submit(['vehicle_id' => $vehicle->getKey()])->assertSessionHasErrors('complaint');
        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => ''])->assertSessionHasErrors('complaint');
        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => '   '])->assertSessionHasErrors('complaint');
    }

    public function test_the_complaint_is_capped_at_two_thousand_characters(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('complaint');

        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => str_repeat('a', 2000)])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('jobs.show', Job::query()->sole()));
    }

    public function test_mileage_must_be_a_non_negative_integer_when_present(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => 'x', 'mileage_in' => -1])->assertSessionHasErrors('mileage_in');
        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => 'x', 'mileage_in' => 12.5])->assertSessionHasErrors('mileage_in');
        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => 'x', 'mileage_in' => 'abc'])->assertSessionHasErrors('mileage_in');
        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => 'x', 'mileage_in' => 0])->assertSessionDoesntHaveErrors();
    }

    public function test_an_empty_mileage_is_treated_as_omitted(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 10_000]);

        $this->submit(['vehicle_id' => $vehicle->getKey(), 'complaint' => 'x', 'mileage_in' => ''])
            ->assertSessionDoesntHaveErrors()
            ->assertSessionMissing('warning');

        $this->assertNull(Job::query()->sole()->mileage_in);
        $this->assertSame(10_000, $vehicle->fresh()->mileage);
    }

    public function test_validation_failures_on_the_appointment_path_return_to_the_prefilled_form(): void
    {
        $appointment = Appointment::factory()->create();
        $form = route('jobs.create', ['appointment' => $appointment->getKey()]);

        $this->from($form)
            ->post(route('jobs.store'), ['appointment_id' => $appointment->getKey(), 'complaint' => ''])
            ->assertRedirect($form)
            ->assertSessionHasErrors('complaint');

        $this->assertTrue($appointment->fresh()->isBooked());
    }
}
