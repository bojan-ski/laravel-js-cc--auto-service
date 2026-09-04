<?php

namespace Tests\Feature\Dashboard;

use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardActiveJobsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function page(): TestResponse
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function job(string $plate, string $state = 'received', ?CarbonImmutable $openedAt = null): Job
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->for($customer)->create(['registration_plate' => $plate]);

        $job = Job::factory()->for($customer)->for($vehicle)->{$state}()->create();

        if ($openedAt !== null) {
            $job->forceFill(['created_at' => $openedAt])->save();
        }

        return $job;
    }

    public function test_only_working_jobs_are_listed(): void
    {
        $this->job('BG100', 'received');
        $this->job('BG200', 'inProgress');
        $this->job('BG300', 'awaitingParts');
        $this->job('BG400', 'completed');
        $this->job('BG500', 'cancelled');

        $page = $this->page();

        $page->assertSee('BG100')->assertSee('BG200')->assertSee('BG300');
        $page->assertDontSee('BG400')->assertDontSee('BG500');
    }

    public function test_the_working_states_come_from_the_enum_predicate(): void
    {
        $this->assertSame(
            [JobStatus::Received, JobStatus::InProgress, JobStatus::AwaitingParts],
            JobStatus::working(),
        );

        foreach (JobStatus::cases() as $status) {
            $this->assertSame(
                $status->isWorking(),
                in_array($status, JobStatus::working(), true),
                "{$status->value} is classified differently by isWorking() and working().",
            );
        }
    }

    /**
     * The board is newest first; this panel is the opposite, because it answers
     * "what is still here" rather than "what came in".
     */
    public function test_jobs_are_ordered_oldest_first(): void
    {
        $now = CarbonImmutable::now();

        $this->job('BG010', 'received', $now->subDays(1));
        $this->job('BG090', 'awaitingParts', $now->subDays(9));
        $this->job('BG050', 'inProgress', $now->subDays(5));

        $this->page()->assertSeeInOrder(['BG090', 'BG050', 'BG010']);
    }

    public function test_fifty_active_jobs_show_ten_rows_and_a_count_of_fifty(): void
    {
        $now = CarbonImmutable::now();

        for ($i = 1; $i <= 50; $i++) {
            $this->job(sprintf('NS%03d', $i), 'received', $now->subMinutes(60 - $i));
        }

        $page = $this->page();

        $page->assertViewHas('jobs', fn ($jobs): bool => $jobs->count() === 10);
        $page->assertViewHas('jobCount', 50);
        $page->assertSee('Active jobs (50)');

        $page->assertSee('NS001')->assertSee('NS010');
        $page->assertDontSee('NS011');
    }

    public function test_the_heading_count_is_the_true_total_not_the_row_cap(): void
    {
        $now = CarbonImmutable::now();

        for ($i = 1; $i <= 14; $i++) {
            $this->job(sprintf('BG9%02d', $i), 'received', $now->subMinutes(20 - $i));
        }

        $this->page()
            ->assertSee('Active jobs (14)')
            ->assertDontSee('Active jobs (10)');
    }

    /**
     * The cap makes the `id` tiebreak load-bearing rather than cosmetic: with the
     * timestamps equal, it decides which jobs are among the ten shown at all.
     *
     * This test documents that intent but does NOT pin it — measured: dropping
     * `orderBy('id')` leaves it green, because MySQL returns primary-key order
     * here regardless. DashboardQueryTest::test_every_panel_query_carries_a_
     * deterministic_tiebreak is the assertion that fails on that mutation.
     */
    public function test_jobs_opened_at_the_same_moment_are_ordered_by_id(): void
    {
        $openedAt = CarbonImmutable::now()->subHour();

        $this->job('BG701', 'received', $openedAt);
        $this->job('BG702', 'received', $openedAt);

        $this->page()->assertSeeInOrder(['BG701', 'BG702']);
    }

    public function test_each_row_links_to_its_job(): void
    {
        $job = $this->job('BG100');

        $this->page()
            ->assertSee(route('jobs.show', $job))
            ->assertSee(__('dashboard.active_jobs.view_job', ['reference' => $job->reference]));
    }

    public function test_the_panel_links_to_the_board(): void
    {
        $this->page()->assertSee(route('jobs.index'));
    }

    public function test_the_panel_states_its_emptiness_when_the_shop_is_empty(): void
    {
        $this->job('BG400', 'completed');

        $this->page()->assertSee(__('dashboard.active_jobs.empty'));
    }
}
