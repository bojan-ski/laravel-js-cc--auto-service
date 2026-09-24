<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_null_email_renders_an_em_dash(): void
    {
        $customer = Customer::factory()->minimal()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('app.value_none'));
    }

    public function test_the_detail_page_renders_the_vehicles_section_and_the_empty_job_history(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('vehicles.section.title'))
            ->assertSee(__('vehicles.section.empty'))
            ->assertSee(__('customers.show.jobs'))
            ->assertSee(__('customers.show.jobs_empty'));
    }

    /**
     * The name is the row's one link, so it is also the link's accessible name.
     * Its ::after is stretched over the positioned row to make the whole row
     * clickable; only a browser can prove the hit-testing (checked by hand).
     */
    public function test_each_row_is_one_link_named_after_its_customer(): void
    {
        $milan = Customer::factory()->create(['name' => 'Milan Petrović']);
        $jelena = Customer::factory()->create(['name' => 'Jelena Ilić']);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->getContent();

        foreach ([$milan, $jelena] as $customer) {
            preg_match('/<tr\b[^>]*>(?:(?!<\/tr>).)*?<\/tr>/s', substr($html, strrpos(substr($html, 0, strpos($html, e($customer->name))), '<tr')), $row);

            $this->assertNotEmpty($row);
            $this->assertMatchesRegularExpression('/^<tr\b[^>]*class="[^"]*\brelative\b/', $row[0]);
            $this->assertMatchesRegularExpression(
                '/<a\b[^>]*href="'.preg_quote(route('customers.show', $customer), '/').'"[^>]*after:absolute after:inset-0[^>]*>'.preg_quote(e($customer->name), '/').'<\/a>/',
                $row[0],
            );
            $this->assertSame(1, preg_match_all('/<a\b/', $row[0]), 'The row carries exactly one link.');
        }

        $this->assertStringNotContainsString('>'.__('app.actions.view').'<', $html);
    }

    public function test_the_table_keeps_its_row_and_cell_roles_when_display_is_overridden(): void
    {
        Customer::factory()->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('role="table"', $html);
        $this->assertStringContainsString('role="rowgroup"', $html);
        $this->assertStringContainsString('role="row"', $html);
        $this->assertStringContainsString('role="cell"', $html);
        $this->assertStringContainsString('role="columnheader"', $html);
    }

    public function test_the_delete_control_names_the_customer_in_its_accessible_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('customers.delete.action', ['name' => 'Milan Petrović']), false);
    }

    public function test_the_delete_confirmation_names_the_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('data-confirm', false)
            ->assertSee(__('customers.delete.confirm', ['name' => 'Milan Petrović']), false);
    }

    public function test_a_mechanic_sees_no_write_controls(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('customers.show', $customer))
            ->assertOk();

        $response->assertDontSee(route('customers.edit', $customer), false);
        $response->assertDontSee('data-confirm', false);

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee(route('customers.create'), false);
    }

    public function test_a_receptionist_sees_edit_but_not_delete(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk();

        $response->assertSee(route('customers.edit', $customer), false);
        $response->assertDontSee('data-confirm', false);
    }

    public function test_an_admin_sees_both_edit_and_delete(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(route('customers.edit', $customer), false)
            ->assertSee('data-confirm', false);
    }

    public function test_the_nav_marks_customers_as_the_current_page_only_on_customer_routes(): void
    {
        $user = User::factory()->receptionist()->create();

        $onIndex = $this->actingAs($user)->get(route('customers.index'))->assertOk()->getContent();
        $onDashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $pattern = '/<a[^>]*href="'.preg_quote(route('customers.index'), '/').'"[^>]*aria-current="page"/';

        $this->assertMatchesRegularExpression($pattern, $onIndex);
        $this->assertDoesNotMatchRegularExpression($pattern, $onDashboard);
    }

    public function test_the_nav_shows_customers_to_every_role(): void
    {
        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee(__('app.nav.customers'));
        }
    }

    public function test_the_paginator_renders_the_published_project_styled_view(): void
    {
        Customer::factory()->count(26)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('bg-surface-dark', false)
            ->assertDontSee('dark:', false);
    }
}
