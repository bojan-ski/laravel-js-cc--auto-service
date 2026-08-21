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

class AppointmentReschedulingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->receptionist()->create());
    }

    public function test_rescheduling_changes_time_duration_and_notes(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-10 09:00:00'),
            'duration_minutes' => 60,
            'notes' => 'Original.',
        ]);

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-12T14:30',
            'duration_minutes' => 120,
            'notes' => 'Moved.',
        ])->assertRedirect()->assertSessionHas('success');

        $appointment->refresh();

        $this->assertTrue(CarbonImmutable::parse('2026-09-12 14:30')->equalTo($appointment->scheduled_at));
        $this->assertSame(120, $appointment->duration_minutes);
        $this->assertSame('Moved.', $appointment->notes);
    }

    public function test_rescheduling_leaves_the_status_booked(): void
    {
        $appointment = Appointment::factory()->create();

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-12T14:30',
            'duration_minutes' => 60,
        ])->assertRedirect();

        $this->assertSame(AppointmentStatus::Booked, $appointment->fresh()->status);
    }

    public function test_a_submitted_vehicle_id_is_ignored(): void
    {
        $appointment = Appointment::factory()->create();
        $originalVehicle = $appointment->vehicle_id;
        $originalCustomer = $appointment->customer_id;

        $otherVehicle = Vehicle::factory()->for(Customer::factory())->create();

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-12T14:30',
            'duration_minutes' => 60,
            'vehicle_id' => $otherVehicle->getKey(),
            'customer_id' => $otherVehicle->customer_id,
        ])->assertRedirect();

        $appointment->refresh();

        $this->assertSame($originalVehicle, $appointment->vehicle_id);
        $this->assertSame($originalCustomer, $appointment->customer_id);
    }

    public function test_the_edit_form_prefills_the_datetime_in_the_input_format(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-10 09:05:00'),
        ]);

        $this->get(route('appointments.edit', $appointment))
            ->assertOk()
            ->assertSee('value="2026-09-10T09:05"', false);
    }

    public function test_the_edit_form_shows_the_vehicle_as_fixed_context(): void
    {
        $appointment = Appointment::factory()->create();

        $this->get(route('appointments.edit', $appointment))
            ->assertOk()
            ->assertSee(__('appointments.form.vehicle_fixed_hint'))
            ->assertDontSee('name="vehicle_id"', false);
    }

    public function test_rescheduling_onto_an_occupied_window_succeeds_with_a_warning(): void
    {
        Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-12 14:00:00'),
            'duration_minutes' => 60,
        ]);

        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-20 09:00:00'),
        ]);

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-12T14:30',
            'duration_minutes' => 60,
        ])->assertRedirect()->assertSessionHas('success')->assertSessionHas('warning');
    }

    public function test_an_appointment_does_not_warn_about_overlapping_itself(): void
    {
        $appointment = Appointment::factory()->create([
            'scheduled_at' => CarbonImmutable::parse('2026-09-12 14:00:00'),
            'duration_minutes' => 60,
        ]);

        $this->put(route('appointments.update', $appointment), [
            'scheduled_at' => '2026-09-12T14:30',
            'duration_minutes' => 60,
        ])->assertRedirect()->assertSessionMissing('warning');
    }
}
