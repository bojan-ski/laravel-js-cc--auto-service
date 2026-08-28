<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_absent_optional_values_render_an_em_dash(): void
    {
        $vehicle = Vehicle::factory()->minimal()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(__('app.value_none'));
    }

    public function test_the_mileage_renders_with_separators_and_a_unit(): void
    {
        $vehicle = Vehicle::factory()->create(['mileage' => 184320]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(__('vehicles.show.mileage_value', ['mileage' => '184,320']));
    }

    public function test_a_vehicle_with_a_soft_deleted_owner_still_renders(): void
    {
        $owner = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($owner)->create();
        $owner->delete();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk();

        $response->assertSee('Milan Petrović');
        $response->assertSee(__('app.deleted'));
        $response->assertDontSee(route('customers.show', $owner), false);
    }

    public function test_a_live_owner_is_a_link_back_to_the_customer(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(route('customers.show', $vehicle->customer), false)
            ->assertDontSee(__('app.deleted'));
    }

    public function test_the_service_history_section_is_present_and_empty(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(__('vehicles.show.history'))
            ->assertSee(__('vehicles.show.history_empty'));
    }

    public function test_a_customer_lists_only_their_own_vehicles(): void
    {
        $customer = Customer::factory()->create();
        Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123-ZZ']);
        Vehicle::factory()->create(['registration_plate' => 'NS887-AB']);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('BG123-ZZ')
            ->assertDontSee('NS887-AB');
    }

    public function test_only_an_admin_sees_the_deleted_vehicles_section(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123-ZZ']);
        $vehicle->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('vehicles.deleted.title'))
            ->assertSee('BG123-ZZ');

        foreach (['mechanic', 'receptionist'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('customers.show', $customer))
                ->assertOk()
                ->assertDontSee(__('vehicles.deleted.title'))
                ->assertDontSee('BG123-ZZ');
        }
    }

    public function test_the_deleted_section_is_absent_when_the_customer_has_no_deleted_vehicles(): void
    {
        $customer = Customer::factory()->create();
        Vehicle::factory()->for($customer)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee(__('vehicles.deleted.title'));
    }

    public function test_the_delete_confirmation_names_the_plate_and_warns_about_the_reserved_plate(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG123-ZZ']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-confirm', false)
            ->assertSee(__('vehicles.delete.confirm', ['plate' => 'BG123-ZZ']), false);
    }

    public function test_a_mechanic_sees_no_write_controls_on_a_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk();

        $response->assertDontSee(route('vehicles.edit', $vehicle), false);
        $response->assertDontSee(route('vehicles.owner.edit', $vehicle), false);
        $response->assertDontSee('data-confirm', false);
    }

    public function test_a_receptionist_sees_edit_but_neither_reassign_nor_delete(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk();

        $response->assertSee(route('vehicles.edit', $vehicle), false);
        $response->assertDontSee(route('vehicles.owner.edit', $vehicle), false);
        $response->assertDontSee('data-confirm', false);
    }

    public function test_an_admin_sees_every_control(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(route('vehicles.edit', $vehicle), false)
            ->assertSee(route('vehicles.owner.edit', $vehicle), false)
            ->assertSee('data-confirm', false);
    }

    public function test_only_roles_that_may_create_see_the_add_vehicle_control(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(route('customers.vehicles.create', $customer), false);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee(route('customers.vehicles.create', $customer), false);
    }

    public function test_the_vehicle_table_keeps_its_row_and_cell_roles_when_display_is_overridden(): void
    {
        $customer = Customer::factory()->create();
        Vehicle::factory()->for($customer)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('role="table"', $html);
        $this->assertStringContainsString('role="rowgroup"', $html);
        $this->assertStringContainsString('role="row"', $html);
        $this->assertStringContainsString('role="cell"', $html);
        $this->assertStringContainsString('role="columnheader"', $html);
    }

    /**
     * WCAG 2.5.3 Label in Name: a control's accessible name must contain its
     * visible label verbatim, or speech input cannot reach it.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function labelInNameProvider(): array
    {
        return [
            'add vehicle' => ['vehicles.section.add', 'vehicles.section.add_for'],
            'view vehicle' => ['app.actions.view', 'vehicles.section.view_vehicle'],
            'restore vehicle' => ['vehicles.deleted.restore', 'vehicles.deleted.restore_vehicle'],
            'edit vehicle' => ['app.actions.edit', 'vehicles.edit.title'],
            'reassign vehicle' => ['vehicles.reassign.action', 'vehicles.reassign.action_vehicle'],
            'delete vehicle' => ['app.actions.delete', 'vehicles.delete.action'],
        ];
    }

    #[DataProvider('labelInNameProvider')]
    public function test_an_accessible_name_contains_its_visible_label(string $visibleKey, string $accessibleKey): void
    {
        $visible = __($visibleKey);
        $accessible = __($accessibleKey, ['plate' => 'BG123-ZZ', 'name' => 'Milan Petrović']);

        $this->assertStringContainsString(
            $visible,
            $accessible,
            "'{$accessibleKey}' must contain the visible label '{$visible}' verbatim.",
        );
    }

    public function test_the_deleted_vehicles_table_labels_its_columns_for_assistive_tech(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $vehicle->delete();

        $customer->load('deletedVehicles');

        $this->actingAs(User::factory()->admin()->create());

        $html = Blade::render('<x-vehicles.deleted-section :customer="$customer" />', ['customer' => $customer]);

        $this->assertSame(3, substr_count($html, 'role="columnheader"'));
        $this->assertStringContainsString(__('vehicles.section.headers.plate'), $html);
        $this->assertStringContainsString(__('vehicles.section.headers.vehicle'), $html);
        $this->assertStringContainsString(__('vehicles.section.headers.actions'), $html);
    }

    public function test_the_restore_button_is_gated_by_its_own_ability(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $vehicle->delete();

        $customer->load('deletedVehicles');

        $this->actingAs(User::factory()->receptionist()->create());

        $html = Blade::render('<x-vehicles.deleted-section :customer="$customer" />', ['customer' => $customer]);

        $this->assertStringNotContainsString(route('vehicles.restore', $vehicle), $html);
        $this->assertStringNotContainsString('data-confirm', $html);
    }

    public function test_the_create_form_shows_the_owner_as_context_rather_than_a_field(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.vehicles.create', $customer))
            ->assertOk()
            ->assertSee('Milan Petrović')
            ->getContent();

        $this->assertStringNotContainsString('name="customer_id"', $html);
    }
}
