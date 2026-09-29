<?php

namespace Tests\Feature\JobItems;

use App\Exceptions\JobLockedException;
use App\Exceptions\JobNotAcceptingLineItems;
use App\Models\Job;
use App\Models\JobItem;
use App\Services\JobItemService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The job-row lock is proven by query order and by a re-check against a state
 * changed after the line was read. Genuine contention is not covered:
 * LazilyRefreshDatabase makes the service's transaction a savepoint, so two
 * real connections never race here.
 */
class JobItemDiscountServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): JobItemService
    {
        return app(JobItemService::class);
    }

    public function test_the_job_row_is_locked_before_the_line_is_written(): void
    {
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service()->setDiscount($item, '10.00');

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lock = $this->firstIndex($queries, '/^select .* from `jobs` .*for update$/');
        $write = $this->firstIndex($queries, '/^update `job_items` set /');

        $this->assertNotNull($lock, "No locked read of the job row:\n".implode("\n", $queries));
        $this->assertNotNull($write, "No write to the line:\n".implode("\n", $queries));
        $this->assertLessThan($write, $lock, "The line was written before the job row was locked:\n".implode("\n", $queries));
        $this->assertStringContainsString('`discount_percent` = ?', $queries[$write]);
    }

    public function test_a_job_invoiced_after_the_line_was_read_refuses_the_discount(): void
    {
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->create();

        Job::query()->whereKey($job->getKey())->update(['locked_at' => now()]);

        try {
            $this->service()->setDiscount($item, '10.00');
            $this->fail('A discount landed on a job whose invoice was computed without it.');
        } catch (JobLockedException) {
            $this->assertSame('0.00', JobItem::query()->findOrFail($item->getKey())->discount_percent);
        }
    }

    public function test_a_job_cancelled_after_the_line_was_read_refuses_the_discount(): void
    {
        $job = Job::factory()->inProgress()->create();
        $item = JobItem::factory()->forJob($job)->create();

        Job::query()->whereKey($job->getKey())->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->expectException(JobNotAcceptingLineItems::class);

        $this->service()->setDiscount($item, '10.00');
    }

    public function test_every_working_state_and_completed_accept_a_discount(): void
    {
        foreach (['received', 'inProgress', 'awaitingParts', 'completed'] as $state) {
            $item = JobItem::factory()->forJob(Job::factory()->{$state}()->create())->create();

            $this->service()->setDiscount($item, '7.50');

            $this->assertSame('7.50', $item->fresh()->discount_percent, $state);
        }
    }

    public function test_only_the_discount_column_is_written(): void
    {
        $item = JobItem::factory()->forJob(Job::factory()->inProgress()->create())->create();

        $saved = $this->service()->setDiscount($item, '15.00');

        $this->assertSame($item, $saved);
        $this->assertSame([], array_diff(array_keys($saved->getChanges()), ['discount_percent', 'updated_at']));
        $this->assertArrayHasKey('discount_percent', $saved->getChanges());
    }

    /**
     * @param  list<string>  $queries
     */
    private function firstIndex(array $queries, string $pattern): ?int
    {
        foreach ($queries as $index => $sql) {
            if (preg_match($pattern, $sql) === 1) {
                return $index;
            }
        }

        return null;
    }
}
