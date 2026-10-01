<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VehicleServiceDatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function receptionist(): User
    {
        return User::factory()->receptionist()->create();
    }

    /**
     * Every input and textarea the rendered form would submit, read from the
     * HTML exactly as a browser would send it untouched.
     *
     * @return array<string, string>
     */
    private function formPayload(string $html): array
    {
        preg_match_all('/<input\b[^>]*\bname="([^"]+)"[^>]*\bvalue="([^"]*)"/s', $html, $inputs, PREG_SET_ORDER);
        preg_match_all('/<textarea\b[^>]*\bname="([^"]+)"[^>]*>(.*?)<\/textarea>/s', $html, $textareas, PREG_SET_ORDER);

        $payload = [];

        foreach ([...$inputs, ...$textareas] as [, $name, $value]) {
            $payload[$name] = html_entity_decode(trim($value), ENT_QUOTES);
        }

        return $payload;
    }

    private function datedVehicle(): Vehicle
    {
        return Vehicle::factory()->create([
            'next_service_on' => '2027-03-15',
            'next_service_mileage' => 200000,
            'registration_expires_on' => '2027-01-31',
        ]);
    }

    public function test_the_create_form_saves_the_three_fields(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->receptionist())
            ->post(route('customers.vehicles.store', $customer), [
                'registration_plate' => 'BG-777-ZZ',
                'make' => 'Opel',
                'model' => 'Astra H',
                'next_service_on' => '2027-03-15',
                'next_service_mileage' => '200000',
                'registration_expires_on' => '2027-01-31',
            ])
            ->assertRedirect();

        $vehicle = Vehicle::query()->where('registration_plate', 'BG-777-ZZ')->sole();

        $this->assertSame('2027-03-15', $vehicle->next_service_on->toDateString());
        $this->assertSame(200000, $vehicle->next_service_mileage);
        $this->assertSame('2027-01-31', $vehicle->registration_expires_on->toDateString());
    }

    public function test_the_create_form_offers_the_three_fields_empty(): void
    {
        $html = $this->actingAs($this->receptionist())
            ->get(route('customers.vehicles.create', Customer::factory()->create()))
            ->assertOk()
            ->getContent();

        $payload = $this->formPayload($html);

        $this->assertSame('', $payload['next_service_on']);
        $this->assertSame('', $payload['next_service_mileage']);
        $this->assertSame('', $payload['registration_expires_on']);
        $this->assertStringContainsString('type="date"', $html);
    }

    public function test_the_edit_form_prefills_each_date_as_y_m_d(): void
    {
        $vehicle = $this->datedVehicle();

        $html = $this->actingAs($this->receptionist())->get(route('vehicles.edit', $vehicle))->assertOk()->getContent();

        $payload = $this->formPayload($html);

        $this->assertSame('2027-03-15', $payload['next_service_on']);
        $this->assertSame('200000', $payload['next_service_mileage']);
        $this->assertSame('2027-01-31', $payload['registration_expires_on']);
    }

    /**
     * Submits the form as rendered with one unrelated change. A date input
     * prefilled with a Carbon's `2027-03-15 00:00:00` shows empty in a browser
     * and would submit nothing, so this is what keeps a stored date alive.
     */
    public function test_an_unrelated_edit_through_the_rendered_form_keeps_all_three_values(): void
    {
        $vehicle = $this->datedVehicle();
        $actor = $this->receptionist();

        $payload = $this->formPayload($this->actingAs($actor)->get(route('vehicles.edit', $vehicle))->getContent());
        $payload['engine'] = '1.9 TDI';

        $this->actingAs($actor)->put(route('vehicles.update', $vehicle), $payload)->assertRedirect(route('vehicles.show', $vehicle));

        $vehicle->refresh();

        $this->assertSame('1.9 TDI', $vehicle->engine);
        $this->assertSame('2027-03-15', $vehicle->next_service_on->toDateString());
        $this->assertSame(200000, $vehicle->next_service_mileage);
        $this->assertSame('2027-01-31', $vehicle->registration_expires_on->toDateString());
    }

    public function test_clearing_a_date_saves_null(): void
    {
        $vehicle = $this->datedVehicle();
        $actor = $this->receptionist();

        $payload = $this->formPayload($this->actingAs($actor)->get(route('vehicles.edit', $vehicle))->getContent());
        $payload['next_service_on'] = '';

        $this->actingAs($actor)->put(route('vehicles.update', $vehicle), $payload)->assertRedirect();

        $this->assertNull($vehicle->fresh()->next_service_on);
    }

    public function test_a_past_date_may_be_saved(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs($this->receptionist())
            ->put(route('vehicles.update', $vehicle), [
                'registration_plate' => $vehicle->registration_plate,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'registration_expires_on' => now()->subYear()->toDateString(),
            ])
            ->assertRedirect(route('vehicles.show', $vehicle));

        $this->assertNotNull($vehicle->fresh()->registration_expires_on);
    }

    public function test_an_invalid_value_renders_its_error_under_its_own_field(): void
    {
        $vehicle = Vehicle::factory()->create();
        $actor = $this->receptionist();

        $this->actingAs($actor)
            ->from(route('vehicles.edit', $vehicle))
            ->put(route('vehicles.update', $vehicle), [
                'registration_plate' => $vehicle->registration_plate,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'next_service_on' => '15/03/2027',
                'next_service_mileage' => '-5',
                'registration_expires_on' => 'soon',
            ])
            ->assertRedirect(route('vehicles.edit', $vehicle));

        $html = $this->actingAs($actor)->get(route('vehicles.edit', $vehicle))->getContent();

        foreach (['next_service_on', 'next_service_mileage', 'registration_expires_on'] as $field) {
            $this->assertMatchesRegularExpression('/<p id="'.$field.'-error"[^>]*>[^<]*'.preg_quote(__('vehicles.form.'.$field), '/').'/', $html, "No error names {$field}.");
            $this->assertMatchesRegularExpression('/name="'.$field.'"[^>]*aria-invalid="true"/s', $html);
        }
    }

    public function test_the_vehicle_page_shows_the_three_rows(): void
    {
        $vehicle = $this->datedVehicle();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSeeInOrder([
                __('vehicles.show.mileage'),
                __('vehicles.show.next_service_on'), '15 Mar 2027',
                __('vehicles.show.next_service_mileage'), '200,000 km',
                __('vehicles.show.registration_expires_on'), '31 Jan 2027',
                __('vehicles.show.notes'),
            ]);
    }

    public function test_the_vehicle_page_shows_the_empty_value_when_unset(): void
    {
        $vehicle = Vehicle::factory()->create(['notes' => 'Has notes']);

        $html = $this->actingAs($this->receptionist())->get(route('vehicles.show', $vehicle))->getContent();

        $section = substr($html, (int) strpos($html, e(__('vehicles.show.next_service_on'))), 2000);

        $this->assertSame(3, substr_count(
            substr($section, 0, (int) strpos($section, e(__('vehicles.show.notes')))),
            __('app.value_none_spoken'),
        ));
    }

    public function test_a_mechanic_cannot_change_the_dates(): void
    {
        $vehicle = $this->datedVehicle();

        $this->actingAs(User::factory()->mechanic()->create())
            ->put(route('vehicles.update', $vehicle), [
                'registration_plate' => $vehicle->registration_plate,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'next_service_on' => '2030-01-01',
            ])
            ->assertForbidden();

        $this->assertSame('2027-03-15', $vehicle->fresh()->next_service_on->toDateString());
    }
}
