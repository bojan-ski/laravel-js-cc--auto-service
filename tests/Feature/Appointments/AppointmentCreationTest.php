<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppointmentCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): AppointmentService
    {
        return app(AppointmentService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validAttributes(): array
    {
        return [
            'scheduled_at' => now()->addDay()->setTime(9, 0),
            'duration_minutes' => 90,
            'notes' => 'Full service.',
        ];
    }

    public function test_a_created_appointment_starts_booked_with_no_transition_timestamps(): void
    {
        $vehicle = Vehicle::factory()->create();
        $bookedBy = User::factory()->receptionist()->create();

        $appointment = $this->service()->create($vehicle, $bookedBy, $this->validAttributes());

        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertNull($appointment->arrived_at);
        $this->assertNull($appointment->no_show_at);
        $this->assertNull($appointment->cancelled_at);
    }

    public function test_created_by_is_the_acting_user(): void
    {
        $vehicle = Vehicle::factory()->create();
        $bookedBy = User::factory()->receptionist()->create();

        $appointment = $this->service()->create($vehicle, $bookedBy, $this->validAttributes());

        $this->assertSame($bookedBy->getKey(), $appointment->created_by);
    }

    public function test_the_supplied_attributes_are_stored(): void
    {
        $vehicle = Vehicle::factory()->create();

        $appointment = $this->service()->create(
            $vehicle,
            User::factory()->receptionist()->create(),
            $this->validAttributes(),
        );

        $this->assertTrue(now()->addDay()->setTime(9, 0)->equalTo($appointment->scheduled_at));
        $this->assertSame(90, $appointment->duration_minutes);
        $this->assertSame('Full service.', $appointment->notes);
        $this->assertSame($vehicle->getKey(), $appointment->vehicle_id);
    }

    public function test_the_customer_is_derived_from_the_vehicles_current_owner(): void
    {
        $owner = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();

        $appointment = $this->service()->create(
            $vehicle,
            User::factory()->receptionist()->create(),
            $this->validAttributes(),
        );

        $this->assertSame($owner->getKey(), $appointment->customer_id);
    }

    public function test_a_customer_supplied_in_the_attributes_is_ignored(): void
    {
        $owner = Customer::factory()->create();
        $impostor = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();

        $appointment = $this->service()->create(
            $vehicle,
            User::factory()->receptionist()->create(),
            [...$this->validAttributes(), 'customer_id' => $impostor->getKey()],
        );

        $this->assertSame($owner->getKey(), $appointment->customer_id);
        $this->assertNotSame($impostor->getKey(), $appointment->customer_id);
    }

    public function test_server_controlled_fields_supplied_in_the_attributes_are_ignored(): void
    {
        $vehicle = Vehicle::factory()->create();
        $otherVehicle = Vehicle::factory()->create();
        $bookedBy = User::factory()->receptionist()->create();
        $impostor = User::factory()->admin()->create();

        $appointment = $this->service()->create($vehicle, $bookedBy, [
            ...$this->validAttributes(),
            'vehicle_id' => $otherVehicle->getKey(),
            'status' => AppointmentStatus::Arrived,
            'created_by' => $impostor->getKey(),
            'arrived_at' => now(),
            'no_show_at' => now(),
            'cancelled_at' => now(),
        ]);

        $this->assertSame($vehicle->getKey(), $appointment->vehicle_id);
        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame($bookedBy->getKey(), $appointment->created_by);
        $this->assertNull($appointment->arrived_at);
        $this->assertNull($appointment->no_show_at);
        $this->assertNull($appointment->cancelled_at);
    }

    public function test_the_customer_is_derived_even_when_the_owner_is_soft_deleted(): void
    {
        $owner = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();
        $owner->delete();

        $appointment = $this->service()->create(
            $vehicle,
            User::factory()->receptionist()->create(),
            $this->validAttributes(),
        );

        $this->assertSame($owner->getKey(), $appointment->customer_id);
    }
}
