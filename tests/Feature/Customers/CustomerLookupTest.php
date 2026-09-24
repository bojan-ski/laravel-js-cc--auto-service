<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CustomerIntakeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function lookup(string $term, string $role = 'receptionist'): TestResponse
    {
        return $this->actingAs(User::factory()->{$role}()->create())
            ->getJson(route('customers.lookup', ['q' => $term]));
    }

    private function milan(): Customer
    {
        $milan = Customer::factory()->create(['name' => 'Milan Petrović', 'phone' => '063 111 222', 'email' => 'milan@example.com']);
        Vehicle::factory()->for($milan)->create(['registration_plate' => 'BG-315-DM', 'make' => 'Škoda', 'model' => 'Octavia II']);
        Vehicle::factory()->for($milan)->create(['registration_plate' => 'NS-887-AA', 'make' => 'Opel', 'model' => 'Astra H']);

        Customer::factory()->create(['name' => 'Jelena Ilić', 'phone' => '064 333 444']);

        return $milan;
    }

    public function test_it_returns_matching_customers_with_their_vehicles_in_resource_shape(): void
    {
        $milan = $this->milan();

        $this->lookup('Petrov')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $milan->id)
            ->assertJsonPath('data.0.name', 'Milan Petrović')
            ->assertJsonPath('data.0.phone', '063 111 222')
            ->assertJsonPath('data.0.email', 'milan@example.com')
            ->assertJsonPath('data.0.vehicles.0.plate', 'BG-315-DM')
            ->assertJsonPath('data.0.vehicles.0.label', 'Škoda Octavia II')
            ->assertJsonPath('data.0.vehicles.1.plate', 'NS-887-AA')
            ->assertJsonPath('data.0.matchedVehicleId', null);
    }

    public function test_it_matches_on_phone(): void
    {
        $milan = $this->milan();

        $this->lookup('111 222')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $milan->id);
    }

    public function test_a_plate_match_names_the_matched_vehicle(): void
    {
        $milan = $this->milan();
        $astra = $milan->vehicles()->where('registration_plate', 'NS-887-AA')->sole();

        $this->lookup('ns887')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.matchedVehicleId', $astra->id);
    }

    public function test_soft_deleted_customers_and_vehicles_are_left_out(): void
    {
        $milan = $this->milan();
        $milan->vehicles()->where('registration_plate', 'NS-887-AA')->sole()->delete();
        Customer::factory()->create(['name' => 'Milan Deleted'])->delete();

        $this->lookup('Milan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.vehicles');
    }

    public function test_results_are_capped(): void
    {
        Customer::factory()->count(CustomerIntakeService::LOOKUP_LIMIT + 3)->sequence(
            fn ($sequence) => ['name' => 'Marko '.$sequence->index],
        )->create();

        $this->lookup('Marko')->assertOk()->assertJsonCount(8, 'data');
    }

    public function test_a_term_shorter_than_two_characters_is_refused(): void
    {
        $this->lookup('M')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_a_mechanic_is_forbidden(): void
    {
        $this->milan();

        $this->lookup('Milan', 'mechanic')->assertForbidden();
    }

    public function test_the_lookup_route_is_not_captured_by_the_customer_binding(): void
    {
        $this->milan();

        $this->lookup('Milan')->assertOk()->assertJsonStructure(['data']);
    }
}
