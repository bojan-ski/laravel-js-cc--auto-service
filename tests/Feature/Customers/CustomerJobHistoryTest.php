<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsJobLinks;
use Tests\Support\AssertsRowLinks;
use Tests\TestCase;

class CustomerJobHistoryTest extends TestCase
{
    use AssertsJobLinks;
    use AssertsRowLinks;
    use LazilyRefreshDatabase;

    public function test_a_customer_with_no_jobs_shows_the_empty_state(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('customers.show.jobs'))
            ->assertSee(__('customers.show.jobs_empty'));
    }

    public function test_the_history_lists_only_that_customers_jobs(): void
    {
        $customer = Customer::factory()->create();
        $mine = Job::factory()->for(Vehicle::factory()->for($customer))->create();
        $theirs = Job::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($this->jobLink($mine), false)
            ->assertDontSee($this->jobLink($theirs), false);
    }

    public function test_the_history_shows_reference_plate_status_and_received_date(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123-ZZ']);
        $job = Job::factory()->for($vehicle)->inProgress()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($job->reference)
            ->assertSee('BG123-ZZ')
            ->assertSee(__('jobs.statuses.in_progress'))
            ->assertSee($job->created_at->isoFormat('D MMM YYYY'))
            ->assertSee($this->jobLink($job), false);
    }

    public function test_each_history_row_is_one_link_to_its_job(): void
    {
        $customer = Customer::factory()->create();
        $job = Job::factory()->for(Vehicle::factory()->for($customer))->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertRowIsOneLinkTo($html, route('jobs.show', $job));
    }

    public function test_the_history_is_newest_first(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();

        $older = Job::factory()->for($vehicle)->create(['created_at' => now()->subDays(3)]);
        $newer = Job::factory()->for($vehicle)->create(['created_at' => now()->subDay()]);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($html, $this->jobLink($older)),
            strpos($html, $this->jobLink($newer)),
            'The newest job must appear before the oldest.',
        );
    }

    public function test_the_history_is_capped_at_ten_rows(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        Job::factory()->for($vehicle)->count(12)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        $rows = 0;

        foreach ($customer->jobs()->get() as $job) {
            $rows += substr_count($html, $this->jobLink($job));
        }

        $this->assertSame(10, $rows);
    }

    public function test_view_all_is_absent_at_exactly_ten_jobs(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        Job::factory()->for($vehicle)->count(10)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee(__('jobs.history.view_all', ['count' => 10]));
    }

    public function test_view_all_appears_past_ten_and_searches_the_customer_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        Job::factory()->for($vehicle)->count(11)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('jobs.history.view_all', ['count' => 11]))
            ->assertSee(route('jobs.index', ['search' => 'Milan Petrović']), false)
            ->assertSee(
                'aria-label="'.e(__('customers.show.jobs_view_all_for', ['count' => 11, 'name' => 'Milan Petrović'])).'"',
                false,
            );
    }

    public function test_a_trashed_vehicle_is_marked_and_never_linked(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123-ZZ']);
        $job = Job::factory()->for($vehicle)->create();
        $vehicle->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('BG123-ZZ')
            ->assertSee(__('app.deleted'))
            ->assertSee($this->jobLink($job), false)
            ->assertDontSee('href="'.route('vehicles.show', $vehicle).'"', false);
    }

    public function test_every_role_sees_the_history(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->for($vehicle)->create();

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('customers.show', $customer))
                ->assertOk()
                ->assertSee($job->reference)
                ->assertSee($this->jobLink($job), false);
        }
    }

    public function test_the_history_table_keeps_its_roles_and_labels_its_columns(): void
    {
        $customer = Customer::factory()->create();
        Job::factory()->for(Vehicle::factory()->for($customer))->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('jobs.history.headers.reference'), $html);
        $this->assertStringContainsString(__('jobs.history.headers.plate'), $html);
        $this->assertStringContainsString(__('jobs.history.headers.status'), $html);
        $this->assertStringContainsString(__('jobs.history.headers.received'), $html);
        $this->assertStringContainsString('role="columnheader"', $html);
        $this->assertStringContainsString('role="cell"', $html);
    }

    /**
     * The label alone would still pass if the badge were swapped for a bare
     * span, so assert the status token classes the badge is there to apply.
     */
    public function test_a_history_status_renders_through_the_badge_in_its_token_colour(): void
    {
        $customer = Customer::factory()->create();
        Job::factory()->for(Vehicle::factory()->for($customer))->awaitingParts()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('bg-status-awaiting-parts/10 text-status-awaiting-parts', false);
    }

    public function test_the_view_all_accessible_name_contains_its_visible_label(): void
    {
        $visible = __('jobs.history.view_all', ['count' => 14]);
        $accessible = __('customers.show.jobs_view_all_for', ['count' => 14, 'name' => 'Milan Petrović']);

        $this->assertStringContainsString($visible, $accessible);
    }
}
