<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsReceptionist(): static
    {
        return $this->actingAs(User::factory()->receptionist()->create());
    }

    public function test_creating_a_customer_redirects_to_the_detail_page_with_a_flash(): void
    {
        $this->actingAsReceptionist()
            ->post(route('customers.store'), [
                'name' => 'Milan Petrović',
                'phone' => '063 123 456',
                'email' => 'milan@example.test',
                'address' => 'Kneza Miloša 12, Beograd',
                'notes' => 'Prefers morning appointments.',
            ])
            ->assertRedirect(route('customers.show', Customer::query()->firstOrFail()))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'name' => 'Milan Petrović',
            'phone' => '063 123 456',
            'email' => 'milan@example.test',
        ]);
    }

    public function test_creating_succeeds_with_only_the_required_fields_and_round_trips_nulls(): void
    {
        $this->actingAsReceptionist()
            ->post(route('customers.store'), [
                'name' => 'Walk-in Customer',
                'phone' => '060 000 000',
            ])
            ->assertRedirect();

        $customer = Customer::query()->firstOrFail();

        $this->assertNull($customer->email);
        $this->assertNull($customer->address);
        $this->assertNull($customer->notes);
    }

    public function test_creating_requires_a_name(): void
    {
        $this->actingAsReceptionist()
            ->post(route('customers.store'), ['phone' => '063 123 456'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_creating_requires_a_phone(): void
    {
        $this->actingAsReceptionist()
            ->post(route('customers.store'), ['name' => 'Milan Petrović'])
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_creating_rejects_a_malformed_email(): void
    {
        $this->actingAsReceptionist()
            ->post(route('customers.store'), [
                'name' => 'Milan Petrović',
                'phone' => '063 123 456',
                'email' => 'not-an-address',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_creating_rejects_over_length_notes(): void
    {
        $this->actingAsReceptionist()
            ->post(route('customers.store'), [
                'name' => 'Milan Petrović',
                'phone' => '063 123 456',
                'notes' => str_repeat('a', 2001),
            ])
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_updating_changes_values_and_keeps_the_id(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);

        $this->actingAsReceptionist()
            ->put(route('customers.update', $customer), [
                'name' => 'Milan Petrović-Ilić',
                'phone' => '063 999 888',
            ])
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('success');

        $customer->refresh();

        $this->assertSame('Milan Petrović-Ilić', $customer->name);
        $this->assertSame('063 999 888', $customer->phone);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_updating_validates(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);

        $this->actingAsReceptionist()
            ->put(route('customers.update', $customer), ['name' => '', 'phone' => ''])
            ->assertSessionHasErrors(['name', 'phone']);

        $this->assertSame('Milan Petrović', $customer->fresh()->name);
    }

    /**
     * Store and update share one CustomerRequest. Exercising the optional fields
     * from the update route too is what would fail if it were ever split back
     * into two classes that then diverged.
     */
    public function test_updating_rejects_a_malformed_email_and_over_length_notes(): void
    {
        $customer = Customer::factory()->create(['email' => 'milan@example.test']);

        $this->actingAsReceptionist()
            ->put(route('customers.update', $customer), [
                'name' => 'Milan Petrović',
                'phone' => '063 123 456',
                'email' => 'not-an-address',
                'notes' => str_repeat('a', 2001),
            ])
            ->assertSessionHasErrors(['email', 'notes']);

        $this->assertSame('milan@example.test', $customer->fresh()->email);
    }

    public function test_deleting_soft_deletes_and_removes_no_row(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($customer);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_a_soft_deleted_customers_detail_page_is_not_reachable(): void
    {
        $customer = Customer::factory()->create();
        $customer->delete();

        $this->actingAsReceptionist()
            ->get(route('customers.show', $customer))
            ->assertNotFound();
    }

    public function test_an_unknown_customer_id_returns_404(): void
    {
        $this->actingAsReceptionist()
            ->get('/customers/999999')
            ->assertNotFound();
    }
}
