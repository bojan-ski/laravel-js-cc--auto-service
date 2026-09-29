<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Http\Requests\Jobs\JobIndexRequest;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ResolvesJobService;
use Tests\TestCase;

class JobBoardFilterTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ResolvesJobService;

    /**
     * @param  array<string, string|bool>  $overrides
     * @return array{status: string, search: string, sort: string, direction: string, mine: bool}
     */
    private function filters(array $overrides = []): array
    {
        return array_merge([
            'status' => '',
            'search' => '',
            'sort' => JobIndexRequest::DEFAULT_SORT,
            'direction' => JobIndexRequest::DEFAULT_DIRECTION,
            'mine' => false,
        ], $overrides);
    }

    private function viewer(): User
    {
        return User::factory()->mechanic()->make();
    }

    public function test_default_order_is_newest_first(): void
    {
        $older = Job::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = Job::factory()->create(['created_at' => now()->subDay()]);

        $page = $this->service()->paginateFiltered($this->filters(), $this->viewer());

        $this->assertSame([$newer->getKey(), $older->getKey()], $page->pluck('id')->all());
    }

    public function test_board_pages_at_twenty_five(): void
    {
        Job::factory()->count(26)->create();

        $page = $this->service()->paginateFiltered($this->filters(), $this->viewer());

        $this->assertCount(25, $page->items());
        $this->assertSame(26, $page->total());
        $this->assertSame(2, $page->lastPage());
    }

    public function test_status_filter_limits_rows_to_that_status(): void
    {
        Job::factory()->received()->count(2)->create();
        $inProgress = Job::factory()->inProgress()->create();

        $page = $this->service()->paginateFiltered($this->filters(['status' => 'in_progress']), $this->viewer());

        $this->assertSame([$inProgress->getKey()], $page->pluck('id')->all());
    }

    public function test_counts_cover_every_status_and_sum_to_all(): void
    {
        Job::factory()->received()->count(3)->create();
        Job::factory()->inProgress()->count(2)->create();
        Job::factory()->cancelled()->create();

        $counts = $this->service()->countByStatus($this->filters(), $this->viewer());

        $this->assertSame([
            'received' => 3,
            'in_progress' => 2,
            'awaiting_parts' => 0,
            'completed' => 0,
            'cancelled' => 1,
        ], $counts);
        $this->assertSame(6, array_sum($counts));
        $this->assertSame(array_keys($counts), array_map(fn (JobStatus $status) => $status->value, JobStatus::cases()));
    }

    public function test_counts_are_one_query(): void
    {
        Job::factory()->count(3)->create();

        DB::enableQueryLog();
        $this->service()->countByStatus($this->filters(), $this->viewer());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }

    public function test_counts_respect_the_search_term_but_not_the_status_filter(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG123AA']);
        Job::factory()->received()->for($vehicle)->create();
        Job::factory()->inProgress()->for($vehicle)->create();
        Job::factory()->received()->create(['vehicle_id' => Vehicle::factory()->create(['registration_plate' => 'NS887BB'])]);

        $counts = $this->service()->countByStatus($this->filters(['search' => 'BG123', 'status' => 'received']), $this->viewer());

        $this->assertSame(1, $counts['received']);
        $this->assertSame(1, $counts['in_progress']);
        $this->assertSame(2, array_sum($counts));
    }

    public function test_search_matches_a_partial_plate_case_insensitively(): void
    {
        $hit = Job::factory()->for(Vehicle::factory()->create(['registration_plate' => 'BG123AA']))->create();
        Job::factory()->for(Vehicle::factory()->create(['registration_plate' => 'NS887BB']))->create();

        $page = $this->service()->paginateFiltered($this->filters(['search' => 'bg12']), $this->viewer());

        $this->assertSame([$hit->getKey()], $page->pluck('id')->all());
    }

    public function test_search_matches_a_partial_customer_name_case_insensitively(): void
    {
        $customer = Customer::factory()->create(['name' => 'Milan Petrović']);
        $hit = Job::factory()->for(Vehicle::factory()->for($customer)->create())->create();
        Job::factory()->create();

        $page = $this->service()->paginateFiltered($this->filters(['search' => 'petrov']), $this->viewer());

        $this->assertSame([$hit->getKey()], $page->pluck('id')->all());
    }

    public function test_search_finds_a_job_whose_customer_is_soft_deleted(): void
    {
        $customer = Customer::factory()->create(['name' => 'Jovana Ilić']);
        $hit = Job::factory()->for(Vehicle::factory()->for($customer)->create())->create();
        $customer->delete();

        $page = $this->service()->paginateFiltered($this->filters(['search' => 'Ilić']), $this->viewer());

        $this->assertSame([$hit->getKey()], $page->pluck('id')->all());
        $this->assertSame(1, array_sum($this->service()->countByStatus($this->filters(['search' => 'Ilić']), $this->viewer())));
    }

    public function test_search_finds_a_job_whose_vehicle_is_soft_deleted(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG440CC']);
        $hit = Job::factory()->for($vehicle)->create();
        $vehicle->delete();

        $page = $this->service()->paginateFiltered($this->filters(['search' => 'BG440']), $this->viewer());

        $this->assertSame([$hit->getKey()], $page->pluck('id')->all());
    }

    public function test_jobs_with_soft_deleted_relations_are_never_dropped_from_an_unfiltered_board(): void
    {
        $job = Job::factory()->create();
        $job->customer->delete();
        $job->vehicle->delete();
        $job->createdBy->delete();

        $page = $this->service()->paginateFiltered($this->filters(), $this->viewer());

        $this->assertSame([$job->getKey()], $page->pluck('id')->all());
        $this->assertSame(1, $page->total());
    }

    public function test_like_wildcards_in_the_term_are_literal(): void
    {
        Job::factory()->for(Vehicle::factory()->create(['registration_plate' => 'BG123AA']))->create();

        $page = $this->service()->paginateFiltered($this->filters(['search' => '%']), $this->viewer());

        $this->assertSame(0, $page->total());
    }

    public function test_status_and_search_compose(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG123AA']);
        $hit = Job::factory()->inProgress()->for($vehicle)->create();
        Job::factory()->received()->for($vehicle)->create();
        Job::factory()->inProgress()->create();

        $page = $this->service()->paginateFiltered($this->filters(['search' => 'BG123', 'status' => 'in_progress']), $this->viewer());

        $this->assertSame([$hit->getKey()], $page->pluck('id')->all());
    }

    public function test_status_and_search_compose_across_a_second_page(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_plate' => 'BG123AA']);
        Job::factory()->inProgress()->for($vehicle)->count(26)->create();
        Job::factory()->inProgress()->create();
        Job::factory()->received()->for($vehicle)->create();

        $filters = $this->filters(['search' => 'BG123', 'status' => 'in_progress']);

        $firstPage = $this->service()->paginateFiltered($filters, $this->viewer());
        $this->assertSame(26, $firstPage->total());
        $this->assertCount(25, $firstPage->items());

        $secondPage = $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', $filters + ['page' => 2]))
            ->assertOk()
            ->viewData('jobs');

        $this->assertCount(1, $secondPage->items());
        $this->assertSame('BG123AA', $secondPage->items()[0]->vehicle->registration_plate);
        $this->assertSame(JobStatus::InProgress, $secondPage->items()[0]->status);
    }

    public function test_pagination_links_preserve_every_filter(): void
    {
        Job::factory()->count(26)->create();
        $user = User::factory()->mechanic()->create();

        $response = $this->actingAs($user)->get(route('jobs.index', [
            'status' => 'received',
            'search' => 'x',
            'sort' => 'plate',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $page = $response->viewData('jobs');

        $this->assertStringContainsString('status=received', $page->url(2));
        $this->assertStringContainsString('search=x', $page->url(2));
        $this->assertStringContainsString('sort=plate', $page->url(2));
        $this->assertStringContainsString('direction=asc', $page->url(2));
    }
}
