<?php

namespace Tests\Feature\JobItems;

use App\Exceptions\JobLockedException;
use App\Models\Job;
use App\Models\JobItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobItemLockGuardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_adding_a_line_to_a_locked_job_throws(): void
    {
        $job = $this->lockedJob();

        $this->expectException(JobLockedException::class);

        try {
            JobItem::factory()->forJob($job)->create();
        } finally {
            $this->assertSame(0, JobItem::query()->where('job_id', $job->getKey())->count());
        }
    }

    public function test_editing_a_line_on_a_locked_job_throws(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['description' => 'Brake service']);
        $this->lock($job);

        $item->description = 'Rewritten after invoicing';

        try {
            $item->save();
            $this->fail('Expected JobLockedException.');
        } catch (JobLockedException $exception) {
            $this->assertTrue($exception->job->is($job));
            $this->assertSame(['description' => 'Rewritten after invoicing'], $exception->attemptedChanges);
        }

        $this->assertSame('Brake service', $item->fresh()->description);
    }

    public function test_the_guard_fires_on_a_direct_update_call(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create(['quantity' => 1.5]);
        $this->lock($job);

        $this->expectException(JobLockedException::class);

        try {
            $item->update(['quantity' => 9]);
        } finally {
            $this->assertSame('1.50', $item->fresh()->quantity);
        }
    }

    public function test_deleting_a_line_from_a_locked_job_throws(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $this->lock($job);

        try {
            $item->delete();
            $this->fail('Expected JobLockedException.');
        } catch (JobLockedException $exception) {
            $this->assertTrue($exception->job->is($job));
        }

        $this->assertNotNull($item->fresh());
    }

    public function test_an_unlocked_job_accepts_adds_edits_and_deletes(): void
    {
        $job = Job::factory()->inProgress()->create();

        $item = JobItem::factory()->forJob($job)->create(['description' => 'Diagnostics']);
        $item->description = 'Diagnostics, extended';
        $item->save();

        $this->assertSame('Diagnostics, extended', $item->fresh()->description);

        $item->delete();

        $this->assertNull(JobItem::query()->find($item->getKey()));
    }

    public function test_a_line_becomes_writable_again_once_the_lock_is_cleared(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();
        $this->lock($job);

        $job->locked_at = null;
        $job->save();

        $item->description = 'Edited after voiding';
        $item->save();

        $this->assertSame('Edited after voiding', $item->fresh()->description);
    }

    private function lockedJob(): Job
    {
        return $this->lock(Job::factory()->inProgress()->create());
    }

    private function lock(Job $job): Job
    {
        $job->locked_at = now();
        $job->save();

        return $job;
    }
}
