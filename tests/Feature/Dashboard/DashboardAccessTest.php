<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_the_dashboard_renders_for_every_staff_role(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    /**
     * Asserted through each panel's link text rather than its heading: "Today" and
     * "Low stock" are short enough to appear by accident, while these strings are
     * unique to their panel and prove the whole panel rendered, footer included.
     */
    #[DataProvider('roleProvider')]
    public function test_all_three_panels_render_for_every_staff_role(string $role): void
    {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.today.title'))
            ->assertSee(__('dashboard.today.view_all'))
            ->assertSee(__('dashboard.active_jobs.view_all'))
            ->assertSee(__('dashboard.low_stock.view_all'));
    }

    /**
     * The panel CONTENT, not just the chrome, for every role. Every other content
     * test in this suite runs as a receptionist, so without this a scope leaking
     * into one of the three read methods — the shape `PartService::pickerOptions()`
     * and `Part::visibleTo()` already use elsewhere — would empty a mechanic's
     * dashboard with the suite fully green.
     */
    #[DataProvider('roleProvider')]
    public function test_every_role_sees_the_same_panel_content(string $role): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123']);

        Appointment::factory()->for($customer)->for($vehicle)->create([
            'scheduled_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);
        Job::factory()->for($customer)->for($vehicle)->received()->create();
        Part::factory()->create(['name' => 'Oil filter', 'quantity_in_stock' => 1, 'reorder_level' => 4]);

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('BG123')
            ->assertSee('Oil filter')
            ->assertSee('Active jobs (1)')
            ->assertSee('Low stock (1)')
            ->assertDontSee(__('dashboard.today.empty'))
            ->assertDontSee(__('dashboard.active_jobs.empty'))
            ->assertDontSee(__('dashboard.low_stock.empty'));
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
