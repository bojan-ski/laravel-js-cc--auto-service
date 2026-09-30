<?php

namespace Tests\Feature\Jobs;

use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\JobTemplate;
use App\Models\JobTemplateItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

/**
 * LazilyRefreshDatabase wraps every test in a transaction, so the apply's own
 * transaction runs as a savepoint here: these tests prove the apply rolls back
 * its own work, not how it behaves under real concurrent contention.
 */
class JobTemplateApplyAtomicityTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    private const int SIGNED_INT_MIN = -2147483648;

    public function test_a_database_failure_on_a_later_line_leaves_no_line_and_no_movement(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();

        $good = $this->stockedPart(10);
        $floored = Part::factory()->create();
        app(StockService::class)->record($floored, self::SIGNED_INT_MIN, StockMovementReason::ManualAdjustment, $mechanic);

        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->labor()->create(['position' => 1]);
        JobTemplateItem::factory()->forTemplate($template)->forPart($good, 2)->create(['position' => 2]);
        JobTemplateItem::factory()->forTemplate($template)->forPart($floored, 1)->create(['position' => 3]);

        $movementsBefore = StockMovement::query()->count();

        try {
            app(JobItemService::class)->applyTemplate($job, $template, $mechanic, null);
            $this->fail('Expected the underflowing part line to fail.');
        } catch (QueryException) {
            //
        }

        $this->assertSame(0, JobItem::query()->where('job_id', $job->getKey())->count());
        $this->assertSame($movementsBefore, StockMovement::query()->count());
        $this->assertSame(10, $good->fresh()->quantity_in_stock);
        $this->assertSame(self::SIGNED_INT_MIN, $floored->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_the_job_row_is_locked_before_the_template_lines_are_read_or_any_line_is_written(): void
    {
        $mechanic = User::factory()->mechanic()->create();
        $job = Job::factory()->inProgress()->create();
        $template = JobTemplate::factory()->create();
        JobTemplateItem::factory()->forTemplate($template)->labor()->create(['position' => 1]);
        JobTemplateItem::factory()->forTemplate($template)->forPart($this->stockedPart(5), 1)->create(['position' => 2]);

        DB::enableQueryLog();
        app(JobItemService::class)->applyTemplate($job, $template, $mechanic, null);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lock = $this->firstIndex($queries, fn (string $sql): bool => str_contains($sql, 'from `jobs`') && str_contains($sql, 'for update'));
        $linesRead = $this->firstIndex($queries, fn (string $sql): bool => str_contains($sql, 'from `job_template_items`'));
        $firstInsert = $this->firstIndex($queries, fn (string $sql): bool => str_starts_with($sql, 'insert into `job_items`'));

        $this->assertNotNull($lock, 'The apply never locked the job row.');
        $this->assertNotNull($linesRead, 'The apply never read the template lines.');
        $this->assertNotNull($firstInsert, 'The apply never inserted a job line.');
        $this->assertLessThan($linesRead, $lock);
        $this->assertLessThan($firstInsert, $lock);
    }

    /**
     * @param  list<string>  $queries
     */
    private function firstIndex(array $queries, callable $matches): ?int
    {
        foreach ($queries as $index => $sql) {
            if ($matches($sql)) {
                return $index;
            }
        }

        return null;
    }

    private function stockedPart(int $quantity): Part
    {
        $part = Part::factory()->create();

        app(StockService::class)->record($part, $quantity, StockMovementReason::Restock, User::factory()->receptionist()->create());

        return $part;
    }
}
