<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Exceptions\InvalidJobTransition;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobCompletionRequiresLineItemsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'mechanic' => ['mechanic'],
            'receptionist' => ['receptionist'],
            'admin' => ['admin'],
        ];
    }

    public function test_the_guard_refuses_a_job_in_progress_with_no_line_items(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->assertFalse($job->hasLineItems());
        $this->assertFalse($job->canComplete());
    }

    public function test_the_guard_allows_the_same_job_once_a_line_exists(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $this->assertTrue($job->canComplete());
    }

    #[DataProvider('roleProvider')]
    public function test_the_complete_route_refuses_a_lineless_job_for_every_role(string $role): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->patch(route('jobs.complete', $job))
            ->assertForbidden();

        $this->assertSame(JobStatus::InProgress, $job->fresh()->status);
        $this->assertNull($job->fresh()->completed_at);
    }

    #[DataProvider('roleProvider')]
    public function test_the_complete_control_is_absent_from_a_lineless_job(string $role): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('action="'.route('jobs.complete', $job).'"', false)
            ->assertSee('action="'.route('jobs.pause', $job).'"', false);
    }

    #[DataProvider('roleProvider')]
    public function test_the_complete_control_appears_once_a_line_is_recorded(string $role): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('action="'.route('jobs.complete', $job).'"', false);
    }

    public function test_a_job_with_one_line_completes_normally(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('jobs.complete', $job))
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_deleting_the_last_line_withdraws_the_complete_control_again(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('action="'.route('jobs.complete', $job).'"', false);

        $this->actingAs($receptionist)
            ->delete(route('job-items.destroy', $item))
            ->assertRedirect(route('jobs.show', $job));

        $this->actingAs($receptionist)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee('action="'.route('jobs.complete', $job).'"', false);

        $this->actingAs($receptionist)
            ->patch(route('jobs.complete', $job))
            ->assertForbidden();

        $this->assertSame(JobStatus::InProgress, $job->fresh()->status);
    }

    public function test_the_service_refuses_a_lineless_job_directly(): void
    {
        $job = Job::factory()->inProgress()->create();

        $this->expectException(InvalidJobTransition::class);

        try {
            app(JobService::class)->complete($job);
        } finally {
            $this->assertSame(JobStatus::InProgress, $job->fresh()->status);
        }
    }

    public function test_the_hint_explains_the_missing_control_and_disappears_once_a_line_exists(): void
    {
        $job = Job::factory()->inProgress()->create();
        $mechanic = User::factory()->mechanic()->create();

        $this->actingAs($mechanic)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee(__('jobs.items.complete_hint'));

        JobItem::factory()->forJob($job)->create();

        $this->actingAs($mechanic)
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee(__('jobs.items.complete_hint'));
    }

    public function test_the_hint_is_absent_on_states_that_could_not_complete_anyway(): void
    {
        $user = User::factory()->receptionist()->create();

        foreach (['received', 'awaitingParts', 'completed', 'cancelled'] as $state) {
            $this->actingAs($user)
                ->get(route('jobs.show', Job::factory()->{$state}()->create()))
                ->assertOk()
                ->assertDontSee(__('jobs.items.complete_hint'));
        }
    }

    public function test_a_locked_job_shows_neither_the_hint_nor_the_control(): void
    {
        $job = Job::factory()->inProgress()->create();
        $job->locked_at = now();
        $job->save();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('jobs.show', $job))
            ->assertOk()
            ->assertDontSee(__('jobs.items.complete_hint'))
            ->assertDontSee('action="'.route('jobs.complete', $job).'"', false);
    }
}
