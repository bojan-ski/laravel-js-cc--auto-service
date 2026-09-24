<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsStaff(): static
    {
        return $this->actingAs(User::factory()->receptionist()->create());
    }

    public function test_customers_are_ordered_by_name_ascending(): void
    {
        Customer::factory()->create(['name' => 'Zoran Zorić']);
        Customer::factory()->create(['name' => 'Ana Anić']);
        Customer::factory()->create(['name' => 'Marko Marić']);

        $names = $this->actingAsStaff()
            ->get(route('customers.index'))
            ->assertOk()
            ->viewData('customers')
            ->pluck('name')
            ->all();

        $this->assertSame(['Ana Anić', 'Marko Marić', 'Zoran Zorić'], $names);
    }

    public function test_the_index_paginates_at_twenty_five(): void
    {
        Customer::factory()->count(26)->create();

        $this->actingAsStaff()
            ->get(route('customers.index'))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->count() === 25
                && $customers->total() === 26
            );

        $this->actingAsStaff()
            ->get(route('customers.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->count() === 1);
    }

    public function test_search_matches_a_partial_name(): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović']);
        Customer::factory()->create(['name' => 'Jelena Ilić']);

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => 'etrov']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1
                && $customers->first()->name === 'Milan Petrović'
            );
    }

    public function test_search_matches_a_partial_phone(): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović', 'phone' => '063 123 456']);
        Customer::factory()->create(['name' => 'Jelena Ilić', 'phone' => '064 987 654']);

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => '987']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1
                && $customers->first()->name === 'Jelena Ilić'
            );
    }

    public function test_search_is_case_insensitive(): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović']);

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => 'MILAN']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1);
    }

    public function test_a_wildcard_in_the_search_term_matches_literally(): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović']);
        Customer::factory()->create(['name' => '100% Auto doo']);

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => '100%']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1
                && $customers->first()->name === '100% Auto doo'
            );
    }

    public function test_a_blank_search_is_treated_as_no_search(): void
    {
        Customer::factory()->count(2)->create();

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => '   ']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 2)
            ->assertViewHas('filters', ['search' => '']);
    }

    public function test_a_search_matching_nothing_renders_the_no_matches_state(): void
    {
        Customer::factory()->create(['name' => 'Milan Petrović']);

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => 'nobody-by-that-name']))
            ->assertOk()
            ->assertSee(__('customers.empty.no_matches.title'))
            ->assertDontSee(__('customers.empty.none.title'));
    }

    public function test_an_empty_shop_renders_the_no_customers_state(): void
    {
        $this->actingAsStaff()
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee(__('customers.empty.none.title'))
            ->assertDontSee(__('customers.empty.no_matches.title'));
    }

    public function test_the_two_empty_states_do_not_share_wording(): void
    {
        $this->assertNotSame(
            __('customers.empty.none.title'),
            __('customers.empty.no_matches.title'),
        );
    }

    public function test_a_search_term_survives_pagination(): void
    {
        Customer::factory()->count(26)->create(['phone' => '063 000 111']);
        Customer::factory()->count(3)->create(['phone' => '011 222 333']);

        $response = $this->actingAsStaff()
            ->get(route('customers.index', ['search' => '063']))
            ->assertOk();

        $response->assertViewHas('customers', fn ($customers) => $customers->total() === 26);
        $response->assertSee('search=063', false);

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => '063', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 26
                && $customers->count() === 1
            );
    }

    public function test_the_search_term_is_reflected_back_into_the_input(): void
    {
        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => 'Petrović']))
            ->assertOk()
            ->assertSee('value="Petrović"', false);
    }

    public function test_an_over_length_search_term_fails_validation(): void
    {
        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => str_repeat('a', 256)]))
            ->assertRedirect()
            ->assertSessionHasErrors('search');
    }

    public function test_a_soft_deleted_customer_is_absent_from_the_index_and_search(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        $customer->delete();

        $this->actingAsStaff()
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('Milan Petrović');

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => 'Petrović']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function vehicleTermProvider(): array
    {
        return [
            'plate as stored' => ['BG-315'],
            'plate without dashes' => ['BG315'],
            'plate lowercase with a space' => ['bg 315'],
            'make' => ['Škoda'],
            'model' => ['Octavia'],
            'make and model' => ['Škoda Octavia'],
            'vin' => ['TMBJJ7NE'],
        ];
    }

    #[DataProvider('vehicleTermProvider')]
    public function test_search_finds_a_customer_by_their_vehicle(string $term): void
    {
        $milan = Customer::factory()->create(['name' => 'Milan Petrović', 'phone' => '063 111 222']);
        Vehicle::factory()->for($milan)->create([
            'registration_plate' => 'BG-315-DM',
            'make' => 'Škoda',
            'model' => 'Octavia II',
            'vin' => 'TMBJJ7NE8K0123456',
        ]);

        $jelena = Customer::factory()->create(['name' => 'Jelena Ilić', 'phone' => '064 333 444']);
        Vehicle::factory()->for($jelena)->create([
            'registration_plate' => 'NS-887-AA',
            'make' => 'Opel',
            'model' => 'Astra H',
            'vin' => 'W0LZZZ07000209458',
        ]);

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => $term]))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1
                && $customers->first()->is($milan)
            );
    }

    public function test_a_soft_deleted_vehicle_does_not_match_its_owner(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG-315-DM'])->delete();

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => 'BG315']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 0);
    }

    public function test_a_punctuation_only_term_does_not_match_every_plate(): void
    {
        foreach (['BG-315-DM', 'NS-887-AA'] as $plate) {
            Vehicle::factory()
                ->for(Customer::factory()->create(['phone' => '063 111 222']))
                ->create(['registration_plate' => $plate, 'make' => 'Opel', 'model' => 'Astra', 'vin' => null]);
        }

        $this->actingAsStaff()
            ->get(route('customers.index', ['search' => '---']))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 0);
    }

    public function test_each_row_lists_the_customers_active_vehicles(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG-315-DM', 'make' => 'Škoda', 'model' => 'Octavia II']);
        Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG-440-FC', 'make' => 'Ford', 'model' => 'Focus']);
        Vehicle::factory()->for($customer)->create(['registration_plate' => 'SM-045-VK'])->delete();

        $this->actingAsStaff()
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee(__('customers.index.headers.vehicles'))
            ->assertSeeInOrder(['BG-315-DM', 'Škoda Octavia II', 'BG-440-FC', 'Ford Focus'])
            ->assertDontSee('SM-045-VK');
    }

    public function test_the_vehicles_column_costs_the_same_queries_however_many_vehicles_there_are(): void
    {
        $this->assertSame($this->indexQueriesWith(1), $this->indexQueriesWith(8));
    }

    private function indexQueriesWith(int $customers): int
    {
        Customer::factory()->count($customers)->has(Vehicle::factory()->count(2))->create();

        $user = User::factory()->receptionist()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('customers.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }
}
