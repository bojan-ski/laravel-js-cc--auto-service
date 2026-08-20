<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VehicleValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_required_fields_are_enforced(): void
    {
        $this->submit(['registration_plate' => '', 'make' => '', 'model' => ''])
            ->assertSessionHasErrors(['registration_plate', 'make', 'model']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_a_plate_already_held_by_an_active_vehicle_is_rejected(): void
    {
        Vehicle::factory()->create(['registration_plate' => 'BG123-ZZ']);

        $this->submit(['registration_plate' => 'BG123-ZZ'])
            ->assertSessionHasErrors('registration_plate');

        $this->assertSame(1, Vehicle::query()->count());
    }

    public function test_a_plate_held_by_a_soft_deleted_vehicle_is_rejected_with_its_own_message(): void
    {
        $owner = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($owner)->create(['registration_plate' => 'BG123-ZZ']);
        $vehicle->delete();

        $this->submit(['registration_plate' => 'BG123-ZZ'])
            ->assertSessionHasErrors([
                'registration_plate' => __('vehicles.validation.plate_deleted', ['owner' => 'Milan Petrović']),
            ]);
    }

    public function test_the_deleted_plate_message_names_an_owner_who_is_also_deleted(): void
    {
        $owner = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($owner)->create(['registration_plate' => 'BG123-ZZ']);
        $vehicle->delete();
        $owner->delete();

        $this->submit(['registration_plate' => 'BG123-ZZ'])
            ->assertSessionHasErrors([
                'registration_plate' => __('vehicles.validation.plate_deleted', ['owner' => 'Milan Petrović']),
            ]);
    }

    public function test_a_vin_already_held_by_an_active_vehicle_is_rejected(): void
    {
        Vehicle::factory()->create(['vin' => 'WVWZZZ1JZ3W000001']);

        $this->submit(['vin' => 'WVWZZZ1JZ3W000001'])
            ->assertSessionHasErrors('vin');
    }

    public function test_a_vin_held_by_a_soft_deleted_vehicle_is_rejected_with_its_own_message(): void
    {
        $owner = Customer::factory()->create(['name' => 'Jelena Ilić']);
        $vehicle = Vehicle::factory()->for($owner)->create(['vin' => 'WVWZZZ1JZ3W000001']);
        $vehicle->delete();

        $this->submit(['vin' => 'WVWZZZ1JZ3W000001'])
            ->assertSessionHasErrors([
                'vin' => __('vehicles.validation.vin_deleted', ['owner' => 'Jelena Ilić']),
            ]);
    }

    public function test_two_vehicles_may_both_have_no_vin(): void
    {
        Vehicle::factory()->create(['vin' => null]);

        $this->submit(['vin' => null])->assertSessionHasNoErrors();

        $this->assertSame(2, Vehicle::query()->count());
    }

    public function test_a_vehicle_keeps_its_own_plate_and_vin_when_edited(): void
    {
        $vehicle = Vehicle::factory()->create([
            'registration_plate' => 'BG123-ZZ',
            'vin' => 'WVWZZZ1JZ3W000001',
        ]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('vehicles.update', $vehicle), [
                'registration_plate' => 'BG123-ZZ',
                'make' => 'Volkswagen',
                'model' => 'Golf V',
                'vin' => 'WVWZZZ1JZ3W000001',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('vehicles.show', $vehicle));
    }

    public function test_the_year_accepts_its_boundaries_and_rejects_what_lies_outside(): void
    {
        $this->submit(['registration_plate' => 'A1', 'year' => 1900])->assertSessionHasNoErrors();
        $this->submit(['registration_plate' => 'A2', 'year' => now()->year + 1])->assertSessionHasNoErrors();

        $this->submit(['registration_plate' => 'A3', 'year' => 1899])->assertSessionHasErrors('year');
        $this->submit(['registration_plate' => 'A4', 'year' => now()->year + 2])->assertSessionHasErrors('year');
    }

    public function test_a_negative_mileage_is_rejected(): void
    {
        $this->submit(['mileage' => -1])->assertSessionHasErrors('mileage');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(array $overrides = []): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('customers.vehicles.store', Customer::factory()->create()), array_merge([
                'registration_plate' => 'NS887-AB',
                'make' => 'Volkswagen',
                'model' => 'Golf IV',
            ], $overrides));
    }
}
