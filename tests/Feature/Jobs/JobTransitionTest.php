<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Exceptions\InvalidJobTransition;
use App\Models\Job;
use App\Models\JobItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ResolvesJobService;
use Tests\TestCase;

class JobTransitionTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ResolvesJobService;

    private const array TRANSITIONS = ['start', 'pause', 'resume', 'complete', 'cancel', 'reopen'];

    private const array TIMESTAMPS = ['started_at', 'awaiting_parts_at', 'completed_at', 'cancelled_at'];

    public function test_start_moves_a_received_job_into_progress(): void
    {
        $job = Job::factory()->received()->create();

        $returned = $this->service()->start($job);

        $this->assertSame($job, $returned);
        $this->assertSame(JobStatus::InProgress, $job->status);
        $this->assertNotNull($job->started_at);
        $this->assertNull($job->awaiting_parts_at);
        $this->assertNull($job->completed_at);
        $this->assertNull($job->cancelled_at);
        $this->assertFalse($job->isDirty());
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'status' => 'in_progress']);
    }

    public function test_pause_marks_a_job_awaiting_parts(): void
    {
        $job = Job::factory()->inProgress()->create();
        $startedAt = $job->started_at;

        $this->service()->pause($job);

        $this->assertSame(JobStatus::AwaitingParts, $job->status);
        $this->assertNotNull($job->awaiting_parts_at);
        $this->assertTrue($startedAt->equalTo($job->started_at));
        $this->assertNull($job->completed_at);
        $this->assertNull($job->cancelled_at);
    }

    public function test_resume_clears_the_pause_timestamp(): void
    {
        $job = Job::factory()->awaitingParts()->create();

        $this->service()->resume($job);

        $this->assertSame(JobStatus::InProgress, $job->status);
        $this->assertNull($job->awaiting_parts_at);
        $this->assertNotNull($job->started_at);
        $this->assertNull($job->fresh()->awaiting_parts_at);
    }

    public function test_pausing_again_after_a_resume_re_sets_the_pause_timestamp(): void
    {
        $job = Job::factory()->awaitingParts()->create();
        $firstPause = $job->awaiting_parts_at;

        $this->service()->resume($job);
        $this->travel(10)->minutes();
        $this->service()->pause($job);

        $this->assertSame(JobStatus::AwaitingParts, $job->status);
        $this->assertNotNull($job->awaiting_parts_at);
        $this->assertTrue($job->awaiting_parts_at->greaterThan($firstPause));
    }

    public function test_complete_finishes_a_job_in_progress(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $this->service()->complete($job);

        $this->assertSame(JobStatus::Completed, $job->status);
        $this->assertNotNull($job->completed_at);
        $this->assertNotNull($job->started_at);
        $this->assertNull($job->cancelled_at);
        $this->assertNull($job->locked_at);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function cancellableStateProvider(): array
    {
        return [
            'received' => ['received'],
            'in progress' => ['inProgress'],
            'awaiting parts' => ['awaitingParts'],
        ];
    }

    #[DataProvider('cancellableStateProvider')]
    public function test_cancel_succeeds_from_every_working_state(string $state): void
    {
        $job = Job::factory()->{$state}()->create();
        $before = $job->only(self::TIMESTAMPS);

        $this->transitionJob('cancel', $job);

        $this->assertSame(JobStatus::Cancelled, $job->status);
        $this->assertNotNull($job->cancelled_at);
        $this->assertEquals($before['started_at'], $job->started_at);
        $this->assertEquals($before['awaiting_parts_at'], $job->awaiting_parts_at);
        $this->assertNull($job->completed_at);
    }

    public function test_reopen_returns_a_completed_job_to_progress_and_clears_completed_at(): void
    {
        $job = Job::factory()->completed()->create();

        $this->service()->reopen($job);

        $this->assertSame(JobStatus::InProgress, $job->status);
        $this->assertNull($job->completed_at);
        $this->assertNotNull($job->started_at);
        $this->assertNull($job->fresh()->completed_at);
    }

    /**
     * Every illegal (state, transition) pair in the matrix.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function illegalTransitionProvider(): array
    {
        $legal = [
            'received' => ['start', 'cancel'],
            'inProgress' => ['pause', 'complete', 'cancel'],
            'awaitingParts' => ['resume', 'cancel'],
            'completed' => ['reopen'],
            'cancelled' => [],
        ];

        $cases = [];

        foreach ($legal as $state => $allowed) {
            foreach (array_diff(self::TRANSITIONS, $allowed) as $transition) {
                $cases["{$transition} from {$state}"] = [$state, $transition];
            }
        }

        return $cases;
    }

    #[DataProvider('illegalTransitionProvider')]
    public function test_an_illegal_transition_throws_and_changes_nothing(string $state, string $transition): void
    {
        $job = Job::factory()->{$state}()->create();
        $snapshot = $job->fresh()->getAttributes();

        try {
            $this->transitionJob($transition, $job);
            $this->fail("Expected {$transition} from {$state} to throw.");
        } catch (InvalidJobTransition $exception) {
            $this->assertSame($transition, $exception->transition);
            $this->assertSame($job->status, $exception->status);
            $this->assertTrue($exception->job->is($job));
        }

        $this->assertSame($snapshot, $job->fresh()->getAttributes());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function transitionProvider(): array
    {
        return array_combine(self::TRANSITIONS, array_map(fn (string $name): array => [$name], self::TRANSITIONS));
    }

    #[DataProvider('transitionProvider')]
    public function test_every_transition_is_refused_on_a_locked_job(string $transition): void
    {
        $job = Job::factory()->locked()->create();

        $this->expectException(InvalidJobTransition::class);

        $this->transitionJob($transition, $job);
    }

    #[DataProvider('transitionProvider')]
    public function test_every_transition_is_refused_on_a_locked_job_in_its_enabling_state(string $transition): void
    {
        $state = match ($transition) {
            'start' => 'received',
            'pause', 'complete' => 'inProgress',
            'resume' => 'awaitingParts',
            'cancel' => 'received',
            'reopen' => 'completed',
        };

        $job = Job::factory()->{$state}()->create(['locked_at' => now()]);
        $snapshot = $job->fresh()->getAttributes();

        $this->expectException(InvalidJobTransition::class);

        try {
            $this->transitionJob($transition, $job);
        } finally {
            $this->assertSame($snapshot, $job->fresh()->getAttributes());
        }
    }

    public function test_the_guard_is_re_checked_under_the_row_lock(): void
    {
        $stale = Job::factory()->received()->create();

        $this->service()->start(Job::findOrFail($stale->getKey()));

        $this->assertSame(JobStatus::Received, $stale->status);

        try {
            $this->service()->start($stale);
            $this->fail('Expected the stale instance to be refused under the lock.');
        } catch (InvalidJobTransition $exception) {
            $this->assertSame(JobStatus::InProgress, $exception->status);
        }

        $this->assertSame(JobStatus::InProgress, $stale->fresh()->status);
    }

    public function test_a_race_between_two_instances_yields_one_success_and_one_refusal(): void
    {
        $first = Job::factory()->inProgress()->create();
        $second = Job::findOrFail($first->getKey());

        $this->transitionJob('cancel', $first);

        $this->expectException(InvalidJobTransition::class);

        try {
            $this->transitionJob('cancel', $second);
        } finally {
            $this->assertSame($first->cancelled_at->toDateTimeString(), $first->fresh()->cancelled_at->toDateTimeString());
        }
    }

    public function test_a_full_lifecycle_runs_end_to_end(): void
    {
        $job = Job::factory()->received()->create();
        JobItem::factory()->forJob($job)->create();
        $service = $this->service();

        $service->start($job);
        $service->pause($job);
        $service->resume($job);
        $service->complete($job);
        $service->reopen($job);
        $service->complete($job);

        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
        $this->assertNotNull($job->started_at);
        $this->assertNull($job->awaiting_parts_at);
        $this->assertNotNull($job->completed_at);
        $this->assertNull($job->cancelled_at);
    }
}
