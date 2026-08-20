<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function routeProvider(): array
    {
        return [
            'index' => ['get', '/customers'],
            'create' => ['get', '/customers/create'],
            'store' => ['post', '/customers'],
            'show' => ['get', '/customers/1'],
            'edit' => ['get', '/customers/1/edit'],
            'update' => ['put', '/customers/1'],
            'destroy' => ['delete', '/customers/1'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_a_guest_is_redirected_to_login(string $method, string $uri): void
    {
        Customer::factory()->create();

        $this->{$method}($uri)->assertRedirect(route('login'));
    }

    public function test_a_mechanic_may_view_the_list_and_a_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('customers.index'))
            ->assertOk();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('customers.show', $customer))
            ->assertOk();
    }

    public function test_a_mechanic_is_forbidden_from_every_write_route(): void
    {
        $customer = Customer::factory()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)->get(route('customers.create'))->assertForbidden();
        $this->actingAs($mechanic)->post(route('customers.store'), [
            'name' => 'Milan Petrović',
            'phone' => '063 123 456',
        ])->assertForbidden();
        $this->actingAs($mechanic)->get(route('customers.edit', $customer))->assertForbidden();
        $this->actingAs($mechanic)->put(route('customers.update', $customer), [
            'name' => 'Milan Petrović',
            'phone' => '063 123 456',
        ])->assertForbidden();
        $this->actingAs($mechanic)->delete(route('customers.destroy', $customer))->assertForbidden();

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_a_receptionist_may_create_and_update(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->get(route('customers.create'))->assertOk();

        $this->actingAs($receptionist)->post(route('customers.store'), [
            'name' => 'Jelena Ilić',
            'phone' => '064 987 654',
        ])->assertRedirect();

        $customer = Customer::query()->firstOrFail();

        $this->actingAs($receptionist)->get(route('customers.edit', $customer))->assertOk();

        $this->actingAs($receptionist)->put(route('customers.update', $customer), [
            'name' => 'Jelena Ilić-Petrović',
            'phone' => '064 987 654',
        ])->assertRedirect();
    }

    public function test_a_receptionist_is_forbidden_from_deleting(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();

        $this->assertNotSoftDeleted($customer);
    }

    public function test_an_admin_may_delete(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted($customer);
    }
}
