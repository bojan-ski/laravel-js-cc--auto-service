<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->receptionist()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Vehicle $vehicle, string $at = '2026-09-10T10:00', int $duration = 60): array
    {
        return [
            'vehicle_id' => $vehicle->getKey(),
            'scheduled_at' => $at,
            'duration_minutes' => $duration,
            'notes' => 'Winter tyres.',
        ];
    }

    public function test_booking_creates_a_booked_appointment(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->post(route('appointments.store'), $this->payload($vehicle))
            ->assertRedirect()
            ->assertSessionHas('success');

        $appointment = Appointment::query()->sole();

        $this->assertSame(AppointmentStatus::Booked, $appointment->status);
        $this->assertSame($vehicle->getKey(), $appointment->vehicle_id);
        $this->assertSame(60, $appointment->duration_minutes);
        $this->assertSame('Winter tyres.', $appointment->notes);
    }

    public function test_booking_assigns_the_vehicles_current_owner(): void
    {
        $owner = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($owner)->create();

        $this->post(route('appointments.store'), $this->payload($vehicle))->assertRedirect();

        $this->assertSame($owner->getKey(), Appointment::query()->sole()->customer_id);
    }

    public function test_the_acting_user_is_recorded_as_the_booker(): void
    {
        $receptionist = User::factory()->receptionist()->create();
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($receptionist)
            ->post(route('appointments.store'), $this->payload($vehicle))
            ->assertRedirect();

        $this->assertSame($receptionist->getKey(), Appointment::query()->sole()->created_by);
    }

    public function test_booking_over_an_existing_appointment_succeeds_with_a_warning(): void
    {
        $existing = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-10 10:30:00'),
            'duration_minutes' => 60,
        ]);

        $this->post(route('appointments.store'), $this->payload(Vehicle::factory()->create()))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('warning');

        $this->assertSame(2, Appointment::query()->count());
        $this->assertTrue($existing->fresh()->isBooked());
    }

    public function test_booking_without_an_overlap_flashes_no_warning(): void
    {
        Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-10 14:00:00'),
            'duration_minutes' => 60,
        ]);

        $this->post(route('appointments.store'), $this->payload(Vehicle::factory()->create()))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');
    }

    public function test_a_back_to_back_booking_does_not_warn(): void
    {
        Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-10 09:00:00'),
            'duration_minutes' => 60,
        ]);

        $this->post(route('appointments.store'), $this->payload(Vehicle::factory()->create()))
            ->assertRedirect()
            ->assertSessionMissing('warning');
    }

    public function test_the_picker_excludes_soft_deleted_vehicles(): void
    {
        $available = Vehicle::factory()->create();
        $deleted = Vehicle::factory()->create();
        $deleted->delete();

        $this->get(route('appointments.create'))
            ->assertOk()
            ->assertSee($available->registration_plate)
            ->assertDontSee($deleted->registration_plate);
    }

    public function test_a_soft_deleted_vehicle_is_rejected_by_validation(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();

        $this->post(route('appointments.store'), $this->payload($vehicle))
            ->assertSessionHasErrors('vehicle_id');

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_a_duration_outside_the_permitted_range_is_rejected(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->post(route('appointments.store'), $this->payload($vehicle, duration: 10))
            ->assertSessionHasErrors('duration_minutes');

        $this->post(route('appointments.store'), $this->payload($vehicle, duration: 481))
            ->assertSessionHasErrors('duration_minutes');
    }

    public function test_booking_in_the_past_is_allowed(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->post(route('appointments.store'), $this->payload($vehicle, at: '2020-01-01T09:00'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Appointment::query()->count());
    }
}
