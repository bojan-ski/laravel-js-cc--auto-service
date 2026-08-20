<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VehicleManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creating_a_vehicle_takes_its_owner_from_the_route(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('customers.vehicles.store', $customer), [
                'registration_plate' => 'BG123-ZZ',
                'make' => 'Volkswagen',
                'model' => 'Golf IV',
            ])
            ->assertRedirect();

        $this->assertSame($customer->id, Vehicle::query()->firstOrFail()->customer_id);
    }

    public function test_a_submitted_customer_id_is_ignored_on_create(): void
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('customers.vehicles.store', $owner), [
                'registration_plate' => 'BG123-ZZ',
                'make' => 'Volkswagen',
                'model' => 'Golf IV',
                'customer_id' => $intruder->id,
            ])
            ->assertRedirect();

        $this->assertSame($owner->id, Vehicle::query()->firstOrFail()->customer_id);
    }

    public function test_a_submitted_customer_id_is_ignored_on_update(): void
    {
        $vehicle = Vehicle::factory()->create();
        $intruder = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->put(route('vehicles.update', $vehicle), [
                'registration_plate' => $vehicle->registration_plate,
                'make' => 'Opel',
                'model' => 'Astra H',
                'customer_id' => $intruder->id,
            ])
            ->assertRedirect(route('vehicles.show', $vehicle));

        $vehicle->refresh();

        $this->assertSame('Opel', $vehicle->make);
        $this->assertNotSame($intruder->id, $vehicle->customer_id);
    }

    public function test_the_plate_is_stored_exactly_as_entered(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('customers.vehicles.store', $customer), [
                'registration_plate' => 'bg 123 žz',
                'make' => 'Volkswagen',
                'model' => 'Golf IV',
            ])
            ->assertRedirect();

        $this->assertSame('bg 123 žz', Vehicle::query()->firstOrFail()->registration_plate);
    }

    public function test_optional_columns_round_trip_null(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->post(route('customers.vehicles.store', $customer), [
                'registration_plate' => 'BG123-ZZ',
                'make' => 'Volkswagen',
                'model' => 'Golf IV',
                'year' => null,
                'vin' => null,
                'engine' => null,
                'mileage' => null,
                'notes' => null,
            ])
            ->assertRedirect();

        $vehicle = Vehicle::query()->firstOrFail();

        $this->assertNull($vehicle->year);
        $this->assertNull($vehicle->vin);
        $this->assertNull($vehicle->engine);
        $this->assertNull($vehicle->mileage);
        $this->assertNull($vehicle->notes);
    }

    public function test_deleting_soft_deletes_and_drops_the_vehicle_from_its_owners_list(): void
    {
        $vehicle = Vehicle::factory()->create();
        $customer = $vehicle->customer;

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('vehicles.destroy', $vehicle))
            ->assertRedirect(route('customers.show', $customer));

        $this->assertSoftDeleted($vehicle);
        $this->assertTrue($customer->vehicles()->get()->isEmpty());
        $this->assertSame(1, $customer->deletedVehicles()->count());
    }

    public function test_restoring_clears_deleted_at_and_changes_nothing_else(): void
    {
        $vehicle = Vehicle::factory()->create();
        $before = $vehicle->only(['customer_id', 'registration_plate', 'make', 'model', 'year', 'vin', 'engine', 'mileage', 'notes']);

        $vehicle->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('vehicles.restore', $vehicle))
            ->assertRedirect(route('customers.show', $vehicle->customer_id));

        $vehicle->refresh();

        $this->assertNull($vehicle->deleted_at);
        $this->assertSame($before, $vehicle->only(array_keys($before)));
    }

    public function test_a_restored_plate_conflicts_as_an_active_vehicle_again(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG123-ZZ']);
        $vehicle->delete();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch(route('vehicles.restore', $vehicle))->assertRedirect();

        $response = $this->actingAs($admin)
            ->post(route('customers.vehicles.store', Customer::factory()->create()), [
                'registration_plate' => 'BG123-ZZ',
                'make' => 'Opel',
                'model' => 'Astra H',
            ]);

        $response->assertSessionHasErrors('registration_plate');

        $this->assertNotSame(
            __('vehicles.validation.plate_deleted', ['owner' => $vehicle->customer->name]),
            session('errors')->first('registration_plate'),
        );
    }

    public function test_a_vehicle_whose_owner_is_soft_deleted_can_still_be_restored(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();
        $vehicle->customer->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('vehicles.restore', $vehicle))
            ->assertRedirect(route('vehicles.show', $vehicle));

        $this->assertNotSoftDeleted($vehicle);
    }

    public function test_restoring_under_a_deleted_owner_lands_on_a_page_that_renders(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();
        $vehicle->customer->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->followingRedirects()
            ->patch(route('vehicles.restore', $vehicle))
            ->assertOk()
            ->assertSee(__('vehicles.show.owner_deleted'));
    }

    public function test_deleting_under_a_deleted_owner_lands_on_a_page_that_renders(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->customer->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->followingRedirects()
            ->delete(route('vehicles.destroy', $vehicle))
            ->assertOk();

        $this->assertSoftDeleted($vehicle);
    }

    public function test_soft_deleting_a_customer_leaves_their_vehicles_intact(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('customers.destroy', $vehicle->customer))
            ->assertRedirect();

        $this->assertNotSoftDeleted($vehicle);
    }
}
