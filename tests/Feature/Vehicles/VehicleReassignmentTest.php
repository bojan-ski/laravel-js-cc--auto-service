<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VehicleReassignmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reassignment_moves_the_vehicle_and_touches_nothing_else(): void
    {
        $vehicle = Vehicle::factory()->create();
        $newOwner = Customer::factory()->create();

        $before = $vehicle->only(['registration_plate', 'make', 'model', 'year', 'vin', 'engine', 'mileage', 'notes']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('vehicles.owner.update', $vehicle), ['customer_id' => $newOwner->id])
            ->assertRedirect(route('vehicles.show', $vehicle));

        $vehicle->refresh();

        $this->assertSame($newOwner->id, $vehicle->customer_id);
        $this->assertSame($before, $vehicle->only(array_keys($before)));
    }

    public function test_reassignment_to_the_current_owner_is_rejected(): void
    {
        $vehicle = Vehicle::factory()->create();
        $owner = $vehicle->customer;

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('vehicles.owner.update', $vehicle), ['customer_id' => $owner->id])
            ->assertSessionHasErrors(['customer_id' => __('vehicles.validation.already_owner')]);

        $this->assertSame($owner->id, $vehicle->fresh()->customer_id);
    }

    public function test_reassignment_to_a_soft_deleted_customer_is_rejected(): void
    {
        $vehicle = Vehicle::factory()->create();
        $newOwner = Customer::factory()->create();
        $newOwner->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('vehicles.owner.update', $vehicle), ['customer_id' => $newOwner->id])
            ->assertSessionHasErrors('customer_id');

        $this->assertNotSame($newOwner->id, $vehicle->fresh()->customer_id);
    }

    public function test_reassignment_to_an_unknown_customer_is_rejected(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('vehicles.owner.update', $vehicle), ['customer_id' => 9999])
            ->assertSessionHasErrors('customer_id');
    }

    public function test_the_reassign_page_names_both_owners_and_states_that_history_is_preserved(): void
    {
        $owner = Customer::factory()->create(['name' => 'Milan Petrović']);
        $candidate = Customer::factory()->create(['name' => 'Jelena Ilić']);
        $vehicle = Vehicle::factory()->for($owner)->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicles.owner.edit', $vehicle))
            ->assertOk();

        $response->assertSee('Milan Petrović');
        $response->assertSee('Jelena Ilić');
        $response->assertSee(__('vehicles.reassign.warning', ['name' => 'Milan Petrović']), false);
        $response->assertSee(__('vehicles.reassign.current_owner'));
    }

    public function test_the_reassign_picker_offers_neither_the_current_owner_nor_a_deleted_customer(): void
    {
        $owner = Customer::factory()->create(['name' => 'Milan Petrović']);
        $deleted = Customer::factory()->create(['name' => 'Gone Away']);
        $deleted->delete();

        $vehicle = Vehicle::factory()->for($owner)->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicles.owner.edit', $vehicle))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<option value="'.$owner->id.'"', $html);
        $this->assertStringNotContainsString('Gone Away', $html);
    }

    public function test_the_reassign_confirmation_carries_the_current_owner_and_a_token_for_the_chosen_one(): void
    {
        $owner = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($owner)->create();

        $html = $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicles.owner.edit', $vehicle))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm-source="customer_id"', $html);
        $this->assertStringContainsString(':selected', $html);
    }
}
