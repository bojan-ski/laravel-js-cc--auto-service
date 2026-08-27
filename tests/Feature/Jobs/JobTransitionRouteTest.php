<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobTransitionRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VERBS = ['start', 'pause', 'resume', 'complete', 'cancel', 'reopen'];

    /**
     * @return array<string, array{0: string}>
     */
    public static function everyRoleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function verbAndRoleProvider(): array
    {
        $cases = [];

        foreach (self::VERBS as $verb) {
            foreach (array_keys(self::everyRoleProvider()) as $role) {
                $cases["{$verb} as {$role}"] = [$verb, $role];
            }
        }

        return $cases;
    }

    public function test_start_moves_a_received_job_into_progress(): void
    {
        $this->freezeTime();

        $job = Job::factory()->received()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.start', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.flash.started', ['reference' => $job->reference]));

        $job->refresh();

        $this->assertSame(JobStatus::InProgress, $job->status);
        $this->assertEquals(now()->startOfSecond(), $job->started_at);
    }

    public function test_pause_moves_a_job_in_progress_to_awaiting_parts(): void
    {
        $this->freezeTime();

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.pause', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.flash.paused', ['reference' => $job->reference]));

        $job->refresh();

        $this->assertSame(JobStatus::AwaitingParts, $job->status);
        $this->assertEquals(now()->startOfSecond(), $job->awaiting_parts_at);
    }

    public function test_resume_returns_the_job_to_progress_and_clears_the_pause_timestamp(): void
    {
        $job = Job::factory()->awaitingParts()->create();

        $this->assertNotNull($job->awaiting_parts_at);

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.resume', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.flash.resumed', ['reference' => $job->reference]));

        $job->refresh();

        $this->assertSame(JobStatus::InProgress, $job->status);
        $this->assertNull($job->awaiting_parts_at);
    }

    public function test_complete_closes_a_job_in_progress(): void
    {
        $this->freezeTime();

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->mechanic()->create())
            ->patch(route('jobs.complete', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.flash.completed', ['reference' => $job->reference]));

        $job->refresh();

        $this->assertSame(JobStatus::Completed, $job->status);
        $this->assertEquals(now()->startOfSecond(), $job->completed_at);
    }

    public function test_cancel_terminates_a_working_job(): void
    {
        $this->freezeTime();

        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('jobs.cancel', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.flash.cancelled', ['reference' => $job->reference]));

        $job->refresh();

        $this->assertSame(JobStatus::Cancelled, $job->status);
        $this->assertEquals(now()->startOfSecond(), $job->cancelled_at);
    }

    public function test_reopen_returns_a_completed_job_to_progress_and_clears_the_completion(): void
    {
        $job = Job::factory()->completed()->create();

        $this->assertNotNull($job->completed_at);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('jobs.reopen', $job))
            ->assertRedirect(route('jobs.show', $job))
            ->assertSessionHas('success', __('jobs.flash.reopened', ['reference' => $job->reference]));

        $job->refresh();

        $this->assertSame(JobStatus::InProgress, $job->status);
        $this->assertNull($job->completed_at);
    }

    #[DataProvider('everyRoleProvider')]
    public function test_a_transition_whose_guard_is_false_is_refused_for_every_role(string $role): void
    {
        $user = User::factory()->{$role}()->create();
        $job = Job::factory()->received()->create();

        foreach (['pause', 'resume', 'complete', 'reopen'] as $verb) {
            $this->actingAs($user)
                ->patch(route("jobs.{$verb}", $job))
                ->assertForbidden();
        }

        $this->assertSame(JobStatus::Received, $job->refresh()->status);
    }

    public function test_a_mechanic_may_not_cancel_or_reopen(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $working = Job::factory()->inProgress()->create();
        $completed = Job::factory()->completed()->create();

        $this->actingAs($mechanic)->patch(route('jobs.cancel', $working))->assertForbidden();
        $this->actingAs($mechanic)->patch(route('jobs.reopen', $completed))->assertForbidden();

        $this->assertSame(JobStatus::InProgress, $working->refresh()->status);
        $this->assertSame(JobStatus::Completed, $completed->refresh()->status);
    }

    #[DataProvider('verbAndRoleProvider')]
    public function test_every_transition_is_refused_on_a_locked_job_for_every_role(string $verb, string $role): void
    {
        $job = Job::factory()->locked()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->patch(route("jobs.{$verb}", $job))
            ->assertForbidden();

        $job->refresh();

        $this->assertSame(JobStatus::Completed, $job->status);
        $this->assertNotNull($job->locked_at);
    }

    public function test_only_the_cancel_control_asks_for_confirmation(): void
    {
        $job = Job::factory()->inProgress()->create();

        $response = $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(__('jobs.actions.confirm_cancel', [
                'reference' => $job->reference,
                'plate' => $job->vehicle->registration_plate,
            ]));

        $this->assertSame(1, substr_count($response->getContent(), 'data-confirm='));
    }
}
