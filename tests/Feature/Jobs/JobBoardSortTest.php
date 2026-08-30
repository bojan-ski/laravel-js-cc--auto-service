<?php

namespace Tests\Feature\Jobs;

use App\Http\Requests\Jobs\JobIndexRequest;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobBoardSortTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, string>  $overrides
     * @return array{status: string, search: string, sort: string, direction: string}
     */
    private function filters(array $overrides = []): array
    {
        return array_merge([
            'status' => '',
            'search' => '',
            'sort' => JobIndexRequest::DEFAULT_SORT,
            'direction' => JobIndexRequest::DEFAULT_DIRECTION,
        ], $overrides);
    }

    /**
     * @return array<int, int>
     */
    private function ids(string $sort, string $direction): array
    {
        return app(JobService::class)
            ->paginateFiltered($this->filters(['sort' => $sort, 'direction' => $direction]))
            ->pluck('id')
            ->all();
    }

    public function test_sorts_by_plate_in_both_directions_including_a_trashed_vehicle(): void
    {
        $c = Job::factory()->for(Vehicle::factory()->create(['registration_plate' => 'NS300CC']))->create();
        $a = Job::factory()->for($trashed = Vehicle::factory()->create(['registration_plate' => 'BG100AA']))->create();
        $b = Job::factory()->for(Vehicle::factory()->create(['registration_plate' => 'KG200BB']))->create();
        $trashed->delete();

        $this->assertSame([$a->getKey(), $b->getKey(), $c->getKey()], $this->ids('plate', 'asc'));
        $this->assertSame([$c->getKey(), $b->getKey(), $a->getKey()], $this->ids('plate', 'desc'));
    }

    public function test_sorts_by_customer_in_both_directions_including_a_trashed_customer(): void
    {
        $zoran = Job::factory()->for(Vehicle::factory()->for(Customer::factory()->create(['name' => 'Zoran']))->create())->create();
        $ana = Job::factory()->for(Vehicle::factory()->for($trashed = Customer::factory()->create(['name' => 'Ana']))->create())->create();
        $milan = Job::factory()->for(Vehicle::factory()->for(Customer::factory()->create(['name' => 'Milan']))->create())->create();
        $trashed->delete();

        $this->assertSame([$ana->getKey(), $milan->getKey(), $zoran->getKey()], $this->ids('customer', 'asc'));
        $this->assertSame([$zoran->getKey(), $milan->getKey(), $ana->getKey()], $this->ids('customer', 'desc'));
    }

    public function test_sorts_by_status_in_lifecycle_order(): void
    {
        $cancelled = Job::factory()->cancelled()->create();
        $awaiting = Job::factory()->awaitingParts()->create();
        $received = Job::factory()->received()->create();
        $completed = Job::factory()->completed()->create();
        $inProgress = Job::factory()->inProgress()->create();

        $lifecycle = [$received->getKey(), $inProgress->getKey(), $awaiting->getKey(), $completed->getKey(), $cancelled->getKey()];

        $this->assertSame($lifecycle, $this->ids('status', 'asc'));
        $this->assertSame(array_reverse($lifecycle), $this->ids('status', 'desc'));
    }

    public function test_sorts_by_received_date_in_both_directions_ignoring_related_timestamps(): void
    {
        $newCustomerOldJob = Job::factory()->create(['created_at' => now()->subDays(3)]);
        $newCustomerOldJob->customer->forceFill(['created_at' => now()])->save();
        $middle = Job::factory()->create(['created_at' => now()->subDays(2)]);
        $newest = Job::factory()->create(['created_at' => now()->subDay()]);
        $newest->customer->forceFill(['created_at' => now()->subYear()])->save();

        $this->assertSame([$newCustomerOldJob->getKey(), $middle->getKey(), $newest->getKey()], $this->ids('received', 'asc'));
        $this->assertSame([$newest->getKey(), $middle->getKey(), $newCustomerOldJob->getKey()], $this->ids('received', 'desc'));
    }

    public function test_ties_break_on_job_id_in_the_same_direction(): void
    {
        $vehicle = Vehicle::factory()->create();
        $first = Job::factory()->for($vehicle)->create(['created_at' => now()->subDay()]);
        $second = Job::factory()->for($vehicle)->create(['created_at' => now()->subDay()]);

        $this->assertSame([$first->getKey(), $second->getKey()], $this->ids('plate', 'asc'));
        $this->assertSame([$second->getKey(), $first->getKey()], $this->ids('plate', 'desc'));
    }

    public function test_sort_controls_preserve_status_and_search_and_drop_the_page(): void
    {
        $owner = Customer::factory()->create(['name' => 'Alexa Nox']);

        Job::factory()
            ->count(26)
            ->received()
            ->for(Vehicle::factory()->for($owner)->create())
            ->create();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', ['status' => 'received', 'search' => 'x', 'page' => 2]));

        $response->assertOk();

        $this->assertSame(
            26,
            $response->viewData('jobs')->total(),
            'The sort headers only render alongside a table, so every seeded job must match the search term.'
        );

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/href="[^"]*status=received[^"]*sort=plate[^"]*direction=asc"/', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*search=x[^"]*sort=plate[^"]*direction=asc"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*sort=plate[^"]*page=/', $html);
    }

    public function test_the_active_header_toggles_direction_and_exposes_aria_sort(): void
    {
        Job::factory()->create();

        $response = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', ['sort' => 'plate', 'direction' => 'asc']));

        $response->assertOk()
            ->assertSee('aria-sort="ascending"', false)
            ->assertDontSee('aria-sort=""', false)
            ->assertDontSee('aria-sort="descending"', false)
            ->assertSee('sort=plate&amp;direction=desc', false);

        $this->assertSame(1, substr_count($response->getContent(), 'aria-sort='));
    }

    public function test_status_tabs_preserve_sort_direction_and_search(): void
    {
        Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', ['search' => 'q', 'sort' => 'customer', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('status=in_progress&amp;search=q&amp;sort=customer&amp;direction=asc', false);
    }
}
