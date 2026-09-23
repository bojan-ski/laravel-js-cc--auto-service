<?php

namespace Tests\Feature\Jobs;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobIntakeViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_walk_in_form_shows_a_vehicle_picker_and_the_walk_in_label(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG123ZZ']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create'))
            ->assertOk()
            ->assertSee(__('jobs.form.create_title'))
            ->assertSee('name="vehicle_id"', false)
            ->assertSee('BG123ZZ')
            ->assertSee(__('jobs.form.submit_walk_in'))
            ->assertDontSee(__('jobs.form.submit_from_appointment'))
            ->assertDontSee('name="appointment_id"', false)
            ->assertSee('name="complaint"', false)
            ->assertSee(__('jobs.form.complaint_placeholder'))
            ->assertSee('name="mileage_in"', false)
            ->assertSee('type="number"', false)
            ->assertSee(route('jobs.store'), false);
    }

    public function test_the_walk_in_picker_excludes_soft_deleted_vehicles(): void
    {
        $kept = Vehicle::factory()->create(['registration_plate' => 'KEPT001']);
        $gone = Vehicle::factory()->create(['registration_plate' => 'GONE001']);
        $gone->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create'))
            ->assertOk()
            ->assertSee($kept->registration_plate)
            ->assertDontSee($gone->registration_plate);
    }

    public function test_the_appointment_form_fixes_the_vehicle_and_names_the_booking(): void
    {
        $appointment = Appointment::factory()->create(['scheduled_at' => now()->addDay()->setTime(10, 30)]);
        $appointment->load(['vehicle', 'customer', 'createdBy']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertOk()
            ->assertSee('name="appointment_id"', false)
            ->assertSee('value="'.$appointment->getKey().'"', false)
            ->assertDontSee('name="vehicle_id"', false)
            ->assertDontSee('<select', false)
            ->assertSee($appointment->vehicle->registration_plate)
            ->assertSee($appointment->customer->name)
            ->assertSee($appointment->createdBy->name)
            ->assertSee(__('jobs.form.submit_from_appointment'))
            ->assertDontSee(__('jobs.form.submit_walk_in'));
    }

    public function test_the_appointment_form_shows_the_last_known_mileage_when_present(): void
    {
        $appointment = Appointment::factory()->create();
        $vehicle = Vehicle::findOrFail($appointment->vehicle_id);
        $vehicle->mileage = 181_400;
        $vehicle->save();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertOk()
            ->assertSee(__('jobs.form.last_known_mileage', ['mileage' => '181,400']))
            ->assertSee('aria-describedby="mileage_in-hint"', false);
    }

    public function test_the_appointment_form_omits_the_mileage_hint_when_unknown(): void
    {
        $appointment = Appointment::factory()->create();
        $vehicle = Vehicle::findOrFail($appointment->vehicle_id);
        $vehicle->mileage = null;
        $vehicle->save();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertOk()
            ->assertDontSee('mileage_in-hint', false);
    }

    public function test_the_appointment_form_names_the_appointments_customer_not_the_vehicles_current_owner(): void
    {
        $appointment = Appointment::factory()->create();
        $bookedFor = $appointment->customer()->first();
        $vehicle = Vehicle::findOrFail($appointment->vehicle_id);
        $buyer = Customer::factory()->create(['name' => 'Buyer McNew']);
        $vehicle->customer()->associate($buyer);
        $vehicle->save();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertOk()
            ->assertSee($bookedFor->name)
            ->assertDontSee('Buyer McNew');
    }

    public function test_old_input_is_restored_after_a_failed_submit(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->from(route('jobs.create'))
            ->post(route('jobs.store'), ['vehicle_id' => $vehicle->getKey(), 'complaint' => '', 'mileage_in' => 123])
            ->assertRedirect(route('jobs.create'));

        $this->get(route('jobs.create'))
            ->assertOk()
            ->assertSee('value="'.$vehicle->getKey().'" selected', false)
            ->assertSee('value="123"', false);
    }
}
