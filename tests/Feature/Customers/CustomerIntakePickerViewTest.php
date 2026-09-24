<?php

namespace Tests\Feature\Customers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerIntakePickerViewTest extends TestCase
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
    public static function formProvider(): array
    {
        return [
            'booking' => ['appointments.create'],
            'walk-in' => ['jobs.create'],
        ];
    }

    #[DataProvider('formProvider')]
    public function test_the_form_carries_the_picker(string $route): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG-315-DM']);

        $html = $this->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('data-customer-picker-url="'.route('customers.lookup').'"', $html);
        $this->assertMatchesRegularExpression('/<div[^>]*data-customer-picker-search[^>]*hidden|<div[^>]*hidden[^>]*data-customer-picker-search/', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*name="vehicle_id"/', $html);
        $this->assertStringContainsString('value="'.$vehicle->getKey().'"', $html);

        foreach (['customer_id', 'new_customer_name', 'new_customer_phone', 'new_customer_email', 'new_vehicle_plate', 'new_vehicle_make', 'new_vehicle_model', 'new_vehicle_year'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "The picker is missing {$field}.");
        }

        foreach (['option-template', 'vehicle-template', 'new-vehicle-template'] as $template) {
            $this->assertMatchesRegularExpression('/<template[^>]*data-customer-picker-'.$template.'/', $html);
        }
    }

    public function test_arriving_from_a_booking_shows_no_picker(): void
    {
        $appointment = Appointment::factory()->create();

        $this->get(route('jobs.create', ['appointment' => $appointment->getKey()]))
            ->assertOk()
            ->assertDontSee('data-customer-picker', false);
    }

    #[DataProvider('formProvider')]
    public function test_a_failed_submit_restores_the_picked_customer_and_vehicle(string $route): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG-315-DM']);
        $store = $route === 'jobs.create' ? route('jobs.store') : route('appointments.store');

        $this->from(route($route))
            ->post($store, ['customer_id' => $customer->getKey(), 'vehicle_id' => $vehicle->getKey()])
            ->assertRedirect(route($route));

        $html = $this->get(route($route))->assertOk()->getContent();

        preg_match('/data-customer-picker-picked="([^"]*)"/', $html, $picked);
        $data = json_decode(html_entity_decode($picked[1] ?? ''), true);

        $this->assertSame($customer->getKey(), $data['id'] ?? null);
        $this->assertSame('Milan Petrović', $data['name']);
        $this->assertSame('BG-315-DM', $data['vehicles'][0]['plate']);
        $this->assertStringContainsString('data-customer-picker-vehicle="'.$vehicle->getKey().'"', $html);
        $this->assertStringNotContainsString('data-customer-picker-new-vehicle-chosen', $html);
    }

    public function test_a_failed_submit_with_a_new_vehicle_remembers_that_choice(): void
    {
        $customer = Customer::factory()->create();

        $this->from(route('appointments.create'))
            ->post(route('appointments.store'), ['customer_id' => $customer->getKey(), 'new_vehicle_plate' => 'BG-900-AA'])
            ->assertRedirect(route('appointments.create'));

        $this->get(route('appointments.create'))
            ->assertOk()
            ->assertSee('data-customer-picker-new-vehicle-chosen', false)
            ->assertSee('value="BG-900-AA"', false);
    }

    public function test_with_nothing_picked_the_picker_carries_no_customer(): void
    {
        $this->get(route('appointments.create'))
            ->assertOk()
            ->assertSee('data-customer-picker-picked="null"', false);
    }
}
