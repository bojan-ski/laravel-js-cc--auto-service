<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Exceptions\JobLockedException;
use App\Models\Job;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class JobLockGuardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saving_any_attribute_on_a_locked_job_throws(): void
    {
        $job = Job::factory()->locked()->create();
        $job->complaint = 'Changed after invoicing';

        try {
            $job->save();
            $this->fail('Expected JobLockedException.');
        } catch (JobLockedException $exception) {
            $this->assertTrue($exception->job->is($job));
            $this->assertSame(['complaint' => 'Changed after invoicing'], $exception->attemptedChanges);
        }

        $this->assertNotSame('Changed after invoicing', $job->fresh()->complaint);
    }

    public function test_a_status_change_on_a_locked_job_throws(): void
    {
        $job = Job::factory()->locked()->create();
        $job->status = JobStatus::InProgress;

        $this->expectException(JobLockedException::class);

        $job->save();
    }

    public function test_the_guard_fires_on_update(): void
    {
        $job = Job::factory()->locked()->create();

        $this->expectException(JobLockedException::class);

        $job->update(['mileage_in' => 1]);
    }

    public function test_the_guard_fires_on_touch(): void
    {
        $job = Job::factory()->locked()->create();

        $this->travel(1)->minute();

        $this->expectException(JobLockedException::class);

        $job->touch();
    }

    public function test_clearing_the_lock_alone_is_permitted(): void
    {
        $job = Job::factory()->locked()->create();
        $job->locked_at = null;
        $job->save();

        $this->assertNull($job->fresh()->locked_at);
    }

    public function test_setting_the_lock_on_an_unlocked_job_is_permitted(): void
    {
        $job = Job::factory()->completed()->create();
        $job->locked_at = now();
        $job->save();

        $this->assertNotNull($job->fresh()->locked_at);
    }

    public function test_clearing_the_lock_and_changing_another_attribute_in_one_save_throws(): void
    {
        $job = Job::factory()->locked()->create();
        $job->locked_at = null;
        $job->complaint = 'Smuggled past the lock';

        try {
            $job->save();
            $this->fail('Expected JobLockedException.');
        } catch (JobLockedException $exception) {
            $this->assertSame(['complaint'], array_keys($exception->attemptedChanges));
        }

        $fresh = $job->fresh();
        $this->assertNotNull($fresh->locked_at);
        $this->assertNotSame('Smuggled past the lock', $fresh->complaint);
    }

    public function test_a_job_that_was_just_unlocked_accepts_writes_on_the_next_save(): void
    {
        $job = Job::factory()->locked()->create();
        $job->locked_at = null;
        $job->save();

        $job->complaint = 'Edited after voiding';
        $job->save();

        $this->assertSame('Edited after voiding', $job->fresh()->complaint);
    }

    public function test_an_unlocked_job_is_unaffected_by_the_guard(): void
    {
        $job = Job::factory()->create();
        $job->complaint = 'Free to edit';
        $job->mileage_in = 55_555;
        $job->save();

        $this->assertSame('Free to edit', $job->fresh()->complaint);
        $this->assertSame(55_555, $job->fresh()->mileage_in);
    }

    public function test_a_job_loaded_without_its_lock_column_refuses_to_save(): void
    {
        $locked = Job::factory()->locked()->create();
        $partial = Job::query()->select(['id', 'complaint'])->findOrFail($locked->getKey());
        $partial->complaint = 'Loaded without locked_at';

        $this->expectException(JobLockedException::class);

        try {
            $partial->save();
        } finally {
            $this->assertNotSame('Loaded without locked_at', $locked->fresh()->complaint);
        }
    }

    public function test_a_job_loaded_with_its_lock_column_still_saves_when_unlocked(): void
    {
        $job = Job::factory()->create();
        $partial = Job::query()->select(['id', 'complaint', 'locked_at'])->findOrFail($job->getKey());
        $partial->complaint = 'Loaded with locked_at';
        $partial->save();

        $this->assertSame('Loaded with locked_at', $job->fresh()->complaint);
    }

    public function test_creating_a_job_already_locked_is_permitted(): void
    {
        $job = Job::factory()->locked()->create();

        $this->assertNotNull($job->fresh()->locked_at);
    }
}
