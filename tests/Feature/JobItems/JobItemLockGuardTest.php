<?php

namespace Tests\Feature\JobItems;

use App\Enums\StockMovementReason;
use App\Exceptions\JobLockedException;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
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

    public function test_adding_a_part_line_to_a_locked_job_throws_and_moves_no_stock(): void
    {
        $job = $this->lockedJob();
        $part = $this->stockedPart();

        $this->expectException(JobLockedException::class);

        try {
            app(JobItemService::class)->addPart($job, $part, $this->actor(), ['quantity' => 2]);
        } finally {
            $this->assertSame(0, JobItem::query()->count());
            $this->assertSame(10, $part->fresh()->quantity_in_stock);
            $this->assertSame(1, StockMovement::query()->count());
        }
    }

    public function test_editing_a_part_lines_quantity_on_a_locked_job_throws_and_moves_no_stock(): void
    {
        $part = $this->stockedPart();
        $item = app(JobItemService::class)->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $this->actor(),
            ['quantity' => 2],
        );
        $this->lock($item->job()->sole());

        $movementsBefore = StockMovement::query()->count();

        $this->expectException(JobLockedException::class);

        try {
            app(JobItemService::class)->update($item, $this->actor(), ['quantity' => 5]);
        } finally {
            $this->assertSame('2.00', $item->fresh()->quantity);
            $this->assertSame(8, $part->fresh()->quantity_in_stock);
            $this->assertSame($movementsBefore, StockMovement::query()->count());
        }
    }

    /**
     * The sharp one: delete() records the return BEFORE removing the line, so the
     * movement really is written and then rolled back by the guard's exception.
     */
    public function test_deleting_a_part_line_from_a_locked_job_throws_and_rolls_the_return_back(): void
    {
        $part = $this->stockedPart();
        $item = app(JobItemService::class)->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $this->actor(),
            ['quantity' => 2],
        );
        $this->lock($item->job()->sole());

        $movementsBefore = StockMovement::query()->count();

        $this->expectException(JobLockedException::class);

        try {
            app(JobItemService::class)->delete($item, $this->actor());
        } finally {
            $this->assertNotNull($item->fresh());
            $this->assertSame(8, $part->fresh()->quantity_in_stock);
            $this->assertSame($movementsBefore, StockMovement::query()->count());
        }
    }

    public function test_every_role_including_admin_is_refused_a_part_line_write_on_a_locked_job(): void
    {
        $part = $this->stockedPart();
        $item = app(JobItemService::class)->addPart(
            Job::factory()->inProgress()->create(),
            $part,
            $this->actor(),
            ['quantity' => 2],
        );
        $job = $this->lock($item->job()->sole());

        foreach (['mechanic', 'receptionist', 'admin'] as $role) {
            $user = User::factory()->{$role}()->create();

            $this->actingAs($user)
                ->post(route('jobs.parts.store', $job), ['part_id' => $part->getKey(), 'quantity' => 1])
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('job-items.update', $item), ['quantity' => '9'])
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('job-items.destroy', $item))
                ->assertForbidden();
        }

        $this->assertSame(1, JobItem::query()->count());
        $this->assertSame(8, $part->fresh()->quantity_in_stock);
        $this->assertSame(2, StockMovement::query()->count());
    }

    private function stockedPart(): Part
    {
        $part = Part::factory()->create();

        app(StockService::class)->record($part, 10, StockMovementReason::Restock, $this->actor());

        return $part;
    }

    private function actor(): User
    {
        return User::factory()->receptionist()->create();
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
