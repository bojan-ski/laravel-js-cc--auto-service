<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobBoardPerformanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_full_page_with_mixed_trashed_relations_issues_a_bounded_number_of_queries(): void
    {
        $jobs = Job::factory()->count(25)->create();

        $jobs->each(function (Job $job, int $index): void {
            JobItem::factory()->forJob($job)->count(2)->create();

            if ($index % 2 === 0) {
                $job->customer->delete();
            }

            if ($index % 3 === 0) {
                $job->vehicle->delete();
            }

            if ($index % 5 === 0) {
                $job->createdBy->delete();
            }
        });

        $user = User::factory()->mechanic()->create();

        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('jobs.index'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()->assertViewHas('jobs', fn ($page) => $page->count() === 25);
        $this->assertCount(8, $queries, implode("\n", array_column($queries, 'query')));
    }

    public function test_the_board_renders_a_job_whose_customer_vehicle_and_creator_are_all_trashed(): void
    {
        $job = Job::factory()->create();
        $job->customer->delete();
        $job->vehicle->delete();
        $job->createdBy->delete();

        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertSee($job->reference)
            ->assertSee($job->vehicle->registration_plate)
            ->assertSee($job->customer->name);
    }
}
