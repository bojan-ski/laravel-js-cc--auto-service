<?php

namespace Tests\Feature;

use App\Models\User;
use App\View\Components\Global\Nav;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class NavItemsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_items_are_jobs_appointments_customers_parts_in_that_order(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'href="'.route('jobs.index').'"',
                'href="'.route('appointments.index').'"',
                'href="'.route('customers.index').'"',
                'href="'.route('parts.index').'"',
            ], false);
    }

    public function test_there_is_no_standalone_dashboard_item(): void
    {
        $this->actingAs(User::factory()->create());

        $labels = array_column((new Nav)->items, 'label');

        $this->assertSame([
            __('app.nav.jobs'),
            __('app.nav.appointments'),
            __('app.nav.customers'),
            __('app.nav.parts'),
        ], $labels);
        $this->assertFalse(Lang::has('app.nav.dashboard'));
    }

    public function test_the_parts_item_is_current_on_every_parts_route(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('parts.create'))
            ->assertOk()
            ->assertSee('href="'.route('parts.index').'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_wordmark_is_a_link_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee(__('app.name'));
    }

    public function test_the_jobs_item_is_current_on_every_jobs_route(): void
    {
        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.create'))
            ->assertOk()
            ->assertSee('href="'.route('jobs.index').'"', false)
            ->assertSee('aria-current="page"', false);
    }
}
