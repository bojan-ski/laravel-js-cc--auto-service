<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\AssertsJobLinks;
use Tests\Support\AssertsRowLinks;
use Tests\TestCase;

class VehicleServiceHistoryTest extends TestCase
{
    use AssertsJobLinks;
    use AssertsRowLinks;
    use LazilyRefreshDatabase;

    public function test_a_vehicle_never_serviced_shows_the_empty_state(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(__('vehicles.show.history'))
            ->assertSee(__('vehicles.show.history_empty'));
    }

    public function test_the_history_lists_only_that_vehicles_jobs(): void
    {
        $vehicle = Vehicle::factory()->create();
        $mine = Job::factory()->for($vehicle)->create();
        $theirs = Job::factory()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee($this->jobLink($mine), false)
            ->assertDontSee($this->jobLink($theirs), false);
    }

    public function test_each_history_row_is_one_link_to_its_job(): void
    {
        $vehicle = Vehicle::factory()->create();
        $job = Job::factory()->for($vehicle)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->getContent();

        $this->assertRowIsOneLinkTo($html, route('jobs.show', $job));
    }

    public function test_the_history_shows_reference_customer_status_date_and_mileage(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->for($vehicle)->completed()->create(['mileage_in' => 184320]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee($job->reference)
            ->assertSee('Milan Petrović')
            ->assertSee(__('jobs.statuses.completed'))
            ->assertSee($job->created_at->isoFormat('D MMM YYYY'))
            ->assertSee(__('jobs.show.mileage_value', ['mileage' => '184,320']))
            ->assertSee($this->jobLink($job), false);
    }

    public function test_a_job_without_intake_mileage_renders_the_absent_value_marker(): void
    {
        $vehicle = Vehicle::factory()->create();
        Job::factory()->for($vehicle)->create(['mileage_in' => null]);

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(__('app.value_none'));
    }

    /**
     * Only an absent mileage is muted; a real reading is body-coloured data.
     */
    public function test_a_present_mileage_is_not_rendered_in_the_muted_colour(): void
    {
        $present = Vehicle::factory()->create();
        Job::factory()->for($present)->create(['mileage_in' => 184320]);

        $absent = Vehicle::factory()->create();
        Job::factory()->for($absent)->create(['mileage_in' => null]);

        $user = User::factory()->receptionist()->create();

        $withReading = $this->actingAs($user)
            ->get(route('vehicles.show', $present))
            ->assertOk()
            ->getContent();

        $withoutReading = $this->actingAs($user)
            ->get(route('vehicles.show', $absent))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('tabular-nums md:table-cell md:px-3 md:py-1.5 text-text-muted', $withReading);
        $this->assertStringContainsString('tabular-nums md:table-cell md:px-3 md:py-1.5 text-text-muted', $withoutReading);
    }

    public function test_the_history_is_newest_first(): void
    {
        $vehicle = Vehicle::factory()->create();

        $older = Job::factory()->for($vehicle)->create(['created_at' => now()->subYears(2)]);
        $newer = Job::factory()->for($vehicle)->create(['created_at' => now()->subMonth()]);

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
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
        $vehicle = Vehicle::factory()->create();
        Job::factory()->for($vehicle)->count(12)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->getContent();

        $rows = 0;

        foreach ($vehicle->jobs()->get() as $job) {
            $rows += substr_count($html, $this->jobLink($job));
        }

        $this->assertSame(10, $rows);
    }

    public function test_view_all_is_absent_at_exactly_ten_jobs(): void
    {
        $vehicle = Vehicle::factory()->create();
        Job::factory()->for($vehicle)->count(10)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee(__('jobs.history.view_all', ['count' => 10]));
    }

    public function test_view_all_appears_past_ten_and_searches_the_plate(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG123-ZZ']);
        Job::factory()->for($vehicle)->count(11)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee(__('jobs.history.view_all', ['count' => 11]))
            ->assertSee(route('jobs.index', ['search' => 'BG123-ZZ']), false)
            ->assertSee(
                'aria-label="'.e(__('vehicles.show.history_view_all_for', ['count' => 11, 'plate' => 'BG123-ZZ'])).'"',
                false,
            );
    }

    public function test_a_trashed_customer_is_marked_and_never_linked(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->for($vehicle)->create();
        $customer->delete();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Milan Petrović')
            ->assertSee(__('app.deleted'))
            ->assertSee($this->jobLink($job), false)
            ->assertDontSee('href="'.route('customers.show', $customer).'"', false);
    }

    public function test_every_role_sees_the_history(): void
    {
        $vehicle = Vehicle::factory()->create();
        $job = Job::factory()->for($vehicle)->create();

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('vehicles.show', $vehicle))
                ->assertOk()
                ->assertSee($job->reference)
                ->assertSee($this->jobLink($job), false);
        }
    }

    public function test_the_history_table_labels_its_five_columns(): void
    {
        $vehicle = Vehicle::factory()->create();
        Job::factory()->for($vehicle)->create();

        $html = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('jobs.history.headers.reference'), $html);
        $this->assertStringContainsString(__('jobs.history.headers.customer'), $html);
        $this->assertStringContainsString(__('jobs.history.headers.status'), $html);
        $this->assertStringContainsString(__('jobs.history.headers.received'), $html);
        $this->assertStringContainsString(__('jobs.history.headers.mileage'), $html);
        $this->assertSame(5, substr_count($html, 'role="columnheader"'));
    }

    /**
     * The label alone would still pass if the badge were swapped for a bare
     * span, so assert the status token classes the badge is there to apply.
     */
    public function test_a_history_status_renders_through_the_badge_in_its_token_colour(): void
    {
        $vehicle = Vehicle::factory()->create();
        Job::factory()->for($vehicle)->completed()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('bg-status-completed/10 text-status-completed', false);
    }

    public function test_the_view_all_accessible_name_contains_its_visible_label(): void
    {
        $visible = __('jobs.history.view_all', ['count' => 9]);
        $accessible = __('vehicles.show.history_view_all_for', ['count' => 9, 'plate' => 'BG123-ZZ']);

        $this->assertStringContainsString($visible, $accessible);
    }
}
