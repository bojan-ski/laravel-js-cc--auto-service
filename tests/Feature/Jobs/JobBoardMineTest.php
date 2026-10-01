<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Http\Requests\Jobs\JobIndexRequest;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobService;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobBoardMineTest extends TestCase
{
    use LazilyRefreshDatabase;

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
            'mine' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, string|bool>  $overrides
     * @return list<int>
     */
    private function serviceIds(User $user, array $overrides = []): array
    {
        return app(JobService::class)->paginateFiltered($this->filters($overrides), $user)->pluck('id')->all();
    }

    /**
     * @param  array<string, string|int>  $query
     */
    private function board(User $user, array $query = []): TestResponse
    {
        return $this->actingAs($user)->get(route('jobs.index', $query + ['mine' => 1]))->assertOk();
    }

    /**
     * @return list<int>
     */
    private function pageIds(TestResponse $response): array
    {
        return $response->viewData('jobs')->pluck('id')->all();
    }

    private function laborLine(Job $job, User $user): JobItem
    {
        return JobItem::factory()->forJob($job)->performedBy($user)->create();
    }

    private function jobInStatus(JobStatus $status): Job
    {
        return Job::factory()->{Str::camel($status->value)}()->create();
    }

    /**
     * @return array<string, array{Closure(User): Job, bool}>
     */
    public static function mineCases(): array
    {
        return [
            'a labor line naming the user' => [
                function (User $user): Job {
                    $job = Job::factory()->inProgress()->create();
                    JobItem::factory()->forJob($job)->performedBy($user)->create();

                    return $job;
                },
                true,
            ],
            'two labor lines naming the user' => [
                function (User $user): Job {
                    $job = Job::factory()->inProgress()->create();
                    JobItem::factory()->forJob($job)->performedBy($user)->count(2)->create();

                    return $job;
                },
                true,
            ],
            'labor lines naming only other people' => [
                function (User $user): Job {
                    $job = Job::factory()->inProgress()->create();
                    JobItem::factory()->forJob($job)->count(2)->create();

                    return $job;
                },
                false,
            ],
            'a part line assigned to the user and no labor line' => [
                function (User $user): Job {
                    $job = Job::factory()->inProgress()->create();
                    JobItem::factory()->forJob($job)->part()->performedBy($user)->create();

                    return $job;
                },
                false,
            ],
            'no lines at all' => [
                fn (User $user): Job => Job::factory()->inProgress()->create(),
                false,
            ],
            'opened by the user and no labor line naming them' => [
                function (User $user): Job {
                    $job = Job::factory()->inProgress()->create(['created_by' => $user->getKey()]);
                    JobItem::factory()->forJob($job)->create();

                    return $job;
                },
                false,
            ],
        ];
    }

    /**
     * @param  Closure(User): Job  $build
     */
    #[DataProvider('mineCases')]
    public function test_what_counts_as_mine(Closure $build, bool $isMine): void
    {
        $user = User::factory()->mechanic()->create();
        $job = $build($user);

        $this->assertContains($job->getKey(), $this->serviceIds($user, ['mine' => false]));

        $expected = $isMine ? [$job->getKey()] : [];

        $this->assertSame($expected, $this->serviceIds($user));
        $this->assertSame($expected, $this->pageIds($this->board($user)));
    }

    public function test_the_part_line_case_is_a_labor_constraint_and_not_a_missing_line(): void
    {
        $user = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $part = JobItem::factory()->forJob($job)->part()->performedBy($user)->create();

        $this->assertSame($user->getKey(), $part->user_id);
        $this->assertSame([], $this->serviceIds($user));

        $this->laborLine($job, $user);

        $this->assertSame([$job->getKey()], $this->serviceIds($user));
    }

    public function test_mine_applies_to_completed_cancelled_and_locked_jobs(): void
    {
        $user = User::factory()->mechanic()->create();

        $completed = Job::factory()->completed()->create();
        $cancelled = Job::factory()->cancelled()->create();
        $locked = Job::factory()->completed()->create();

        foreach ([$completed, $cancelled, $locked] as $job) {
            $this->laborLine($job, $user);
        }

        $locked->forceFill(['locked_at' => now()])->save();

        $expected = [$completed->getKey(), $cancelled->getKey(), $locked->getKey()];

        $this->assertEqualsCanonicalizing($expected, $this->serviceIds($user));
        $this->assertEqualsCanonicalizing($expected, $this->pageIds($this->board($user)));
    }

    public function test_a_job_with_several_of_my_labor_lines_appears_and_counts_once(): void
    {
        $user = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->performedBy($user)->count(3)->create();

        $page = app(JobService::class)->paginateFiltered($this->filters(), $user);
        $counts = app(JobService::class)->countByStatus($this->filters(), $user);

        $this->assertSame(1, $page->total());
        $this->assertCount(1, $page->items());
        $this->assertSame(1, $counts[JobStatus::InProgress->value]);
        $this->assertSame(1, array_sum($counts));

        $response = $this->board($user);

        $this->assertSame(1, $response->viewData('jobs')->total());
        $this->assertSame([$job->getKey()], $this->pageIds($response));
        $this->assertSame(1, array_sum($response->viewData('counts')));
    }

    /**
     * The completed job of "mine" is owned by a pinned name for the reason
     * given on test_mine_and_the_search_narrow_together.
     */
    public function test_tab_counts_under_mine_count_only_my_jobs_and_respect_the_search(): void
    {
        $user = User::factory()->mechanic()->create();
        $plate = Vehicle::factory()->create(['registration_plate' => 'BG-MINE-1']);

        $this->laborLine(Job::factory()->received()->for($plate)->create(), $user);
        $this->laborLine(Job::factory()->inProgress()->for($plate)->create(), $user);
        $this->laborLine(Job::factory()->completed()->for(Vehicle::factory()->for(Customer::factory()->create(['name' => 'Petar Jovanović'])))->create(), $user);
        Job::factory()->received()->for($plate)->count(2)->create();
        Job::factory()->cancelled()->create();

        $counts = app(JobService::class)->countByStatus($this->filters(), $user);

        $this->assertSame([
            'received' => 1,
            'in_progress' => 1,
            'awaiting_parts' => 0,
            'completed' => 1,
            'cancelled' => 0,
        ], $counts);

        $searched = app(JobService::class)->countByStatus($this->filters(['search' => 'MINE']), $user);

        $this->assertSame([
            'received' => 1,
            'in_progress' => 1,
            'awaiting_parts' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ], $searched);

        $response = $this->board($user, ['search' => 'MINE']);

        $this->assertSame($searched, $response->viewData('counts'));
        $this->assertSame(array_sum($searched), $response->viewData('jobs')->total());
    }

    /**
     * @return array<string, array{JobStatus}>
     */
    public static function statuses(): array
    {
        return collect(JobStatus::cases())->mapWithKeys(fn (JobStatus $status) => [$status->value => [$status]])->all();
    }

    #[DataProvider('statuses')]
    public function test_mine_combines_with_each_status(JobStatus $status): void
    {
        $user = User::factory()->mechanic()->create();

        $mine = [];

        foreach (JobStatus::cases() as $each) {
            $job = $this->jobInStatus($each);
            $this->laborLine($job, $user);
            $mine[$each->value] = $job->getKey();
            $this->jobInStatus($each);
        }

        $this->assertSame([$mine[$status->value]], $this->serviceIds($user, ['status' => $status->value]));
        $this->assertSame([$mine[$status->value]], $this->pageIds($this->board($user, ['status' => $status->value])));
    }

    /**
     * The search matches the plate OR the customer's name, so the unrelated job
     * of "mine" is owned by a pinned name: Faker's en_US first names include
     * Carmine, Minerva, Wilhelmine and Yasmine, any of which matched "MINE"
     * and made this test fail at random (found in spec 36's review).
     */
    public function test_mine_and_the_search_narrow_together(): void
    {
        $user = User::factory()->mechanic()->create();
        $plate = Vehicle::factory()->create(['registration_plate' => 'BG-MINE-1']);

        $hit = Job::factory()->for($plate)->create();
        $this->laborLine($hit, $user);
        $this->laborLine(Job::factory()->for(Vehicle::factory()->for(Customer::factory()->create(['name' => 'Petar Jovanović'])))->create(), $user);
        Job::factory()->for($plate)->create();

        $this->assertSame([$hit->getKey()], $this->serviceIds($user, ['search' => 'MINE']));
        $this->assertSame([$hit->getKey()], $this->pageIds($this->board($user, ['search' => 'MINE'])));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sorts(): array
    {
        $cases = [];

        foreach (JobIndexRequest::SORTS as $sort) {
            foreach (['asc', 'desc'] as $direction) {
                $cases["{$sort} {$direction}"] = [$sort, $direction];
            }
        }

        return $cases;
    }

    #[DataProvider('sorts')]
    public function test_every_sort_orders_the_mine_subset_as_it_orders_the_board(string $sort, string $direction): void
    {
        $user = User::factory()->mechanic()->create();
        $statuses = [JobStatus::Completed, JobStatus::Received, JobStatus::Cancelled, JobStatus::InProgress];

        foreach ($statuses as $index => $status) {
            $job = Job::factory()->{Str::camel($status->value)}()->create(['created_at' => now()->subDays($index + 1)]);
            $this->laborLine($job, $user);
            Job::factory()->{Str::camel($status->value)}()->create(['created_at' => now()->subDays($index + 1)->subHour()]);
        }

        $mine = $this->serviceIds($user, ['sort' => $sort, 'direction' => $direction]);
        $everyone = $this->serviceIds($user, ['sort' => $sort, 'direction' => $direction, 'mine' => false]);

        $this->assertCount(4, $mine);
        $this->assertSame(array_values(array_intersect($everyone, $mine)), $mine);
        $this->assertSame($mine, $this->pageIds($this->board($user, ['sort' => $sort, 'direction' => $direction])));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    #[DataProvider('roles')]
    public function test_mine_lists_the_jobs_of_any_role_holding_a_labor_line(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $job = Job::factory()->create();
        $this->laborLine($job, $user);
        Job::factory()->count(2)->create();

        $this->board($user)
            ->assertSeeText($job->reference)
            ->assertDontSeeText(__('jobs.board.empty.none_mine.title'));

        $this->assertSame([$job->getKey()], $this->pageIds($this->board($user)));
    }

    #[DataProvider('roles')]
    public function test_a_role_holding_no_labor_line_sees_the_none_mine_state(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        Job::factory()->count(2)->create();

        $this->board($user)
            ->assertSeeText(__('jobs.board.empty.none_mine.title'))
            ->assertSeeText(__('jobs.board.empty.none_mine.body'));
    }

    #[DataProvider('roles')]
    public function test_mine_never_widens_what_a_role_sees(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $this->laborLine(Job::factory()->create(), $user);
        $this->laborLine(Job::factory()->cancelled()->create(), $user);
        Job::factory()->count(3)->create();

        $mine = $this->pageIds($this->board($user));
        $everyone = $this->pageIds($this->actingAs($user)->get(route('jobs.index'))->assertOk());

        $this->assertCount(2, $mine);
        $this->assertCount(5, $everyone);
        $this->assertSame([], array_diff($mine, $everyone));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function offQueries(): array
    {
        return [
            'absent' => [[]],
            'zero' => [['mine' => '0']],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('offQueries')]
    public function test_an_absent_or_zero_mine_means_off(array $query): void
    {
        $user = User::factory()->mechanic()->create();
        $this->laborLine(Job::factory()->create(), $user);
        Job::factory()->count(2)->create();

        $response = $this->actingAs($user)->get(route('jobs.index', $query))->assertOk();

        $this->assertFalse($response->viewData('filters')['mine']);
        $this->assertCount(3, $this->pageIds($response));
    }

    public function test_one_turns_mine_on(): void
    {
        $user = User::factory()->mechanic()->create();

        $this->assertTrue($this->board($user)->viewData('filters')['mine']);
    }

    public function test_an_invalid_mine_value_fails_validation_like_an_unknown_status(): void
    {
        $this->actingAs(User::factory()->mechanic()->create())
            ->get(route('jobs.index', ['mine' => 'yes']))
            ->assertRedirect()
            ->assertSessionHasErrors('mine');
    }

    public function test_an_empty_board_shows_none_before_any_filtered_state(): void
    {
        $user = User::factory()->mechanic()->create();

        $this->board($user, ['search' => 'zz', 'status' => JobStatus::Completed->value])
            ->assertSeeText(__('jobs.board.empty.none.title'))
            ->assertDontSeeText(__('jobs.board.empty.no_matches.title'))
            ->assertDontSeeText(__('jobs.board.empty.none_mine.title'))
            ->assertDontSeeText(__('jobs.board.empty.none_in_status.title'));
    }

    public function test_an_applied_search_shows_no_matches_before_none_mine(): void
    {
        $user = User::factory()->mechanic()->create();
        Job::factory()->create();

        $this->board($user, ['search' => 'zz-no-such-plate'])
            ->assertSeeText(__('jobs.board.empty.no_matches.title'))
            ->assertDontSeeText(__('jobs.board.empty.none_mine.title'))
            ->assertDontSeeText(__('jobs.board.empty.none_in_status.title'));
    }

    public function test_mine_shows_none_mine_before_none_in_status(): void
    {
        $user = User::factory()->mechanic()->create();
        Job::factory()->received()->create();

        $this->board($user, ['status' => JobStatus::Completed->value])
            ->assertSeeText(__('jobs.board.empty.none_mine.title'))
            ->assertDontSeeText(__('jobs.board.empty.none_in_status.title'))
            ->assertDontSeeText(__('jobs.board.empty.no_matches.title'));
    }

    public function test_an_empty_status_tab_without_mine_shows_none_in_status(): void
    {
        $user = User::factory()->mechanic()->create();
        Job::factory()->received()->create();

        $this->actingAs($user)
            ->get(route('jobs.index', ['status' => JobStatus::Completed->value]))
            ->assertOk()
            ->assertSeeText(__('jobs.board.empty.none_in_status.title'))
            ->assertDontSeeText(__('jobs.board.empty.none_mine.title'));
    }
}
