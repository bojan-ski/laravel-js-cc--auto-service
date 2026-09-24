<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerPhoneUniquenessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->receptionist()->create());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sameNumberProvider(): array
    {
        return [
            'as stored' => ['063 123 456'],
            'dashes' => ['063-123-456'],
            'no separators' => ['063123456'],
            'brackets and slash' => ['(063) 123/456'],
        ];
    }

    #[DataProvider('sameNumberProvider')]
    public function test_a_new_customer_may_not_take_an_existing_phone(string $phone): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović', 'phone' => '063 123 456']);

        $this->post(route('customers.store'), ['name' => 'Someone Else', 'phone' => $phone])
            ->assertSessionHasErrors(['phone' => __('customers.validation.phone_taken', ['name' => 'Milan Petrović'])]);

        $this->assertSame(1, Customer::query()->count());
    }

    public function test_a_different_number_is_accepted(): void
    {
        Customer::factory()->create(['phone' => '063 123 456']);

        $this->post(route('customers.store'), ['name' => 'Someone Else', 'phone' => '063 123 457'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Customer::query()->count());
    }

    public function test_a_customer_may_keep_their_own_phone_on_edit(): void
    {
        $customer = Customer::factory()->create(['phone' => '063 123 456']);

        $this->put(route('customers.update', $customer), ['name' => 'Renamed', 'phone' => '063-123-456'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $customer->fresh()->name);
    }

    public function test_an_edit_may_not_take_another_customers_phone(): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović', 'phone' => '063 123 456']);
        $customer = Customer::factory()->create(['phone' => '064 999 999']);

        $this->put(route('customers.update', $customer), ['name' => $customer->name, 'phone' => '063 123 456'])
            ->assertSessionHasErrors('phone');

        $this->assertSame('064 999 999', $customer->fresh()->phone);
    }

    public function test_a_deleted_customers_phone_is_free(): void
    {
        Customer::factory()->create(['phone' => '063 123 456'])->delete();

        $this->post(route('customers.store'), ['name' => 'Someone Else', 'phone' => '063 123 456'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_phone_with_no_digits_is_not_matched_against_everyone(): void
    {
        Customer::factory()->create(['phone' => '---']);

        $this->post(route('customers.store'), ['name' => 'Someone Else', 'phone' => '+ -'])
            ->assertSessionHasNoErrors();
    }
}
