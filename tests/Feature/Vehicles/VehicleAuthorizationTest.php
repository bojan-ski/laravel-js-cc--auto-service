<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routeProvider(): array
    {
        return [
            'nested create' => ['get', '/customers/1/vehicles/create'],
            'nested store' => ['post', '/customers/1/vehicles'],
            'show' => ['get', '/vehicles/1'],
            'edit' => ['get', '/vehicles/1/edit'],
            'update' => ['put', '/vehicles/1'],
            'destroy' => ['delete', '/vehicles/1'],
            'owner edit' => ['get', '/vehicles/1/owner/edit'],
            'owner update' => ['patch', '/vehicles/1/owner'],
            'restore' => ['patch', '/vehicles/1/restore'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_a_guest_is_redirected_to_login(string $method, string $uri): void
    {
        Vehicle::factory()->create();

        $this->{$method}($uri)->assertRedirect(route('login'));
    }

    public function test_a_mechanic_may_view_a_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk();
    }

    public function test_a_mechanic_is_forbidden_from_every_write_route(): void
    {
        $vehicle = Vehicle::factory()->create();
        $customer = $vehicle->customer;
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('customers.vehicles.create', $customer))->assertForbidden();
        $this->actingAs($mechanic)->post(route('customers.vehicles.store', $customer), $this->validPayload())->assertForbidden();
        $this->actingAs($mechanic)->get(route('vehicles.edit', $vehicle))->assertForbidden();
        $this->actingAs($mechanic)->put(route('vehicles.update', $vehicle), $this->validPayload())->assertForbidden();
        $this->actingAs($mechanic)->delete(route('vehicles.destroy', $vehicle))->assertForbidden();
        $this->actingAs($mechanic)->get(route('vehicles.owner.edit', $vehicle))->assertForbidden();
        $this->actingAs($mechanic)->patch(route('vehicles.owner.update', $vehicle), [
            'customer_id' => Customer::factory()->create()->id,
        ])->assertForbidden();

        $vehicle->delete();

        $this->actingAs($mechanic)->patch(route('vehicles.restore', $vehicle))->assertForbidden();

        $this->assertDatabaseCount('vehicles', 1);
        $this->assertSoftDeleted($vehicle);
    }

    public function test_a_receptionist_may_create_and_update(): void
    {
        $customer = Customer::factory()->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->get(route('customers.vehicles.create', $customer))->assertOk();

        $this->actingAs($receptionist)
            ->post(route('customers.vehicles.store', $customer), $this->validPayload())
            ->assertRedirect();

        $vehicle = Vehicle::query()->firstOrFail();

        $this->actingAs($receptionist)->get(route('vehicles.edit', $vehicle))->assertOk();

        $this->actingAs($receptionist)
            ->put(route('vehicles.update', $vehicle), $this->validPayload(['make' => 'Opel']))
            ->assertRedirect();

        $this->assertSame('Opel', $vehicle->fresh()->make);
    }

    public function test_a_receptionist_is_forbidden_from_deleting_reassigning_and_restoring(): void
    {
        $vehicle = Vehicle::factory()->create();
        $newOwner = Customer::factory()->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->delete(route('vehicles.destroy', $vehicle))->assertForbidden();
        $this->actingAs($receptionist)->get(route('vehicles.owner.edit', $vehicle))->assertForbidden();
        $this->actingAs($receptionist)->patch(route('vehicles.owner.update', $vehicle), [
            'customer_id' => $newOwner->id,
        ])->assertForbidden();

        $this->assertNotSoftDeleted($vehicle);

        $vehicle->delete();

        $this->actingAs($receptionist)->patch(route('vehicles.restore', $vehicle))->assertForbidden();

        $this->assertSoftDeleted($vehicle);
    }

    public function test_an_admin_may_delete_reassign_and_restore(): void
    {
        $vehicle = Vehicle::factory()->create();
        $newOwner = Customer::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('vehicles.owner.edit', $vehicle))->assertOk();

        $this->actingAs($admin)
            ->patch(route('vehicles.owner.update', $vehicle), ['customer_id' => $newOwner->id])
            ->assertRedirect(route('vehicles.show', $vehicle));

        $this->actingAs($admin)
            ->delete(route('vehicles.destroy', $vehicle))
            ->assertRedirect(route('customers.show', $newOwner));

        $this->assertSoftDeleted($vehicle);

        $this->actingAs($admin)
            ->patch(route('vehicles.restore', $vehicle))
            ->assertRedirect(route('customers.show', $newOwner));

        $this->assertNotSoftDeleted($vehicle);
    }

    public function test_an_unknown_vehicle_returns_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/vehicles/9999')
            ->assertNotFound();
    }

    public function test_a_soft_deleted_vehicle_is_unreachable_by_url(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('vehicles.show', $vehicle))->assertNotFound();
        $this->actingAs($admin)->get(route('vehicles.edit', $vehicle))->assertNotFound();
        $this->actingAs($admin)->get(route('vehicles.owner.edit', $vehicle))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'registration_plate' => 'BG123-ZZ',
            'make' => 'Volkswagen',
            'model' => 'Golf IV',
        ], $overrides);
    }
}
