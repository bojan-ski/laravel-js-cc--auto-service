<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class JobBoardViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, string|int>  $query
     */
    private function board(array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? User::factory()->mechanic()->create())
            ->get(route('jobs.index', $query));
    }

    public function test_every_status_renders_its_own_badge_token(): void
    {
        foreach (['received', 'inProgress', 'awaitingParts', 'completed', 'cancelled'] as $state) {
            Job::factory()->{$state}()->create();
        }

        $response = $this->board()->assertOk();

        foreach (JobStatus::cases() as $status) {
            $response->assertSee($status->label());
            $response->assertSee('text-'.$status->colorToken(), false);
        }
    }

    public function test_the_row_shows_plate_reference_vehicle_and_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => 'BG123AA', 'make' => 'VW', 'model' => 'Golf IV']);
        $job = Job::factory()->for($vehicle)->create();

        $this->board()
            ->assertOk()
            ->assertSeeInOrder(['BG123AA', $job->reference])
            ->assertSee('VW Golf IV')
            ->assertSee('Milan Petrović')
            ->assertSee('<span class="md:hidden">VW Golf IV &middot; </span>Milan Petrović', false);
    }

    public function test_the_assigned_column_renders_an_accessible_em_dash_on_every_row(): void
    {
        Job::factory()->count(3)->create();

        $html = $this->board()->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, __('app.value_none_spoken')));
    }

    public function test_no_monetary_value_appears_on_the_board(): void
    {
        Job::factory()->count(2)->create();

        $this->board()
            ->assertOk()
            ->assertDontSee('unit_price')
            ->assertDontSee('RSD')
            ->assertDontSee('€')
            ->assertDontSee('total');
    }

    public function test_the_header_is_sticky_and_screen_reader_only_below_md(): void
    {
        Job::factory()->create();

        $this->board()->assertOk()->assertSee('sr-only border-b border-border-strong bg-surface-muted md:not-sr-only md:sticky md:top-0 md:table-header-group', false);
    }

    public function test_the_no_jobs_empty_state_offers_a_new_job_to_front_office_only(): void
    {
        $this->board([], User::factory()->receptionist()->create())
            ->assertOk()
            ->assertSee(__('jobs.board.empty.none.title'))
            ->assertSee('href="'.route('jobs.create').'"', false);

        $this->board()
            ->assertOk()
            ->assertSee(__('jobs.board.empty.none.title'))
            ->assertDontSee('href="'.route('jobs.create').'"', false);
    }

    public function test_the_none_in_status_empty_state_clears_only_the_status(): void
    {
        Job::factory()->received()->create();

        $this->board(['status' => 'cancelled', 'sort' => 'plate', 'direction' => 'asc'])
            ->assertOk()
            ->assertSee(__('jobs.board.empty.none_in_status.title'))
            ->assertDontSee(__('jobs.board.empty.no_matches.title'))
            ->assertSee('href="'.e(route('jobs.index', ['sort' => 'plate', 'direction' => 'asc'])).'"', false);
    }

    public function test_the_no_matches_empty_state_wins_over_status_and_keeps_the_term(): void
    {
        Job::factory()->received()->create();

        $this->board(['status' => 'received', 'search' => 'ZZ999'])
            ->assertOk()
            ->assertSee(__('jobs.board.empty.no_matches.title'))
            ->assertDontSee(__('jobs.board.empty.none_in_status.title'))
            ->assertSee('value="ZZ999"', false)
            ->assertSee('href="'.e(route('jobs.index', ['status' => 'received', 'sort' => 'received', 'direction' => 'desc'])).'"', false);
    }

    public function test_the_three_empty_states_use_different_wording(): void
    {
        $titles = [
            __('jobs.board.empty.none.title'),
            __('jobs.board.empty.none_in_status.title'),
            __('jobs.board.empty.no_matches.title'),
        ];

        $this->assertCount(3, array_unique($titles));
    }

    public function test_the_search_form_carries_status_sort_and_direction_as_hidden_inputs(): void
    {
        Job::factory()->inProgress()->create();

        $this->board(['status' => 'in_progress', 'sort' => 'customer', 'direction' => 'asc', 'search' => 'mi'])
            ->assertOk()
            ->assertSee('name="status" value="in_progress"', false)
            ->assertSee('name="sort" value="customer"', false)
            ->assertSee('name="direction" value="asc"', false)
            ->assertSee('value="mi"', false);
    }

    public function test_tab_counts_are_rendered_beside_each_label(): void
    {
        Job::factory()->received()->count(2)->create();
        Job::factory()->completed()->create();

        $this->board()
            ->assertOk()
            ->assertSeeInOrder([__('jobs.board.tab_all'), '3', JobStatus::Received->label(), '2', JobStatus::Completed->label(), '1'])
            ->assertSee('<span class="sr-only">'.trans_choice('jobs.board.count', 2, ['count' => 2]).'</span>', false)
            ->assertSee('<span class="sr-only">'.trans_choice('jobs.board.count', 0, ['count' => 0]).'</span>', false);
    }

    public function test_the_active_tabs_count_matches_the_rows_actually_returned(): void
    {
        Job::factory()->received()->count(2)->create();
        Job::factory()->inProgress()->create();

        $response = $this->board(['status' => 'received'])->assertOk();

        $jobs = $response->viewData('jobs');
        $counts = $response->viewData('counts');

        $this->assertSame(2, $jobs->total());
        $this->assertSame($jobs->total(), $counts['received']);
        $this->assertSame(3, array_sum($counts));
    }

    public function test_the_new_job_button_is_shown_to_front_office_only(): void
    {
        Job::factory()->create();

        $this->board([], User::factory()->admin()->create())->assertSee(__('jobs.board.new'));
        $this->board()->assertDontSee(__('jobs.board.new'));
    }

    public function test_each_row_links_to_the_job(): void
    {
        $job = Job::factory()->create();

        $this->board()
            ->assertOk()
            ->assertSee('href="'.route('jobs.show', $job).'"', false)
            ->assertSee($job->reference);
    }
}
