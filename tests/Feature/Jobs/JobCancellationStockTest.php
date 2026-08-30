<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InvalidJobTransition;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsStockInvariant;
use Tests\Support\ResolvesJobService;
use Tests\TestCase;

class JobCancellationStockTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;
    use ResolvesJobService;

    public function test_cancelling_returns_one_job_return_per_part_line(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $pads = $this->stockedPart(10);
        $fluid = $this->stockedPart(6);

        $this->items()->addPart($job, $pads, $actor, ['quantity' => 2]);
        $this->items()->addPart($job, $fluid, $actor, ['quantity' => 3]);

        $this->service()->cancel($job, $actor);

        $returns = StockMovement::query()
            ->where('reason', StockMovementReason::JobReturn)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $returns);
        $this->assertSame([2, 3], array_map('intval', $returns->pluck('quantity_change')->all()));
        $this->assertSame($actor->getKey(), $returns->first()->user_id);
        $this->assertStockMatchesLedger();
    }

    public function test_stock_afterwards_equals_stock_before_the_job_consumed_anything(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(10);

        $this->items()->addPart($job, $part, $actor, ['quantity' => 4]);

        $this->assertSame(6, $part->fresh()->quantity_in_stock);

        $this->service()->cancel($job, $actor);

        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    public function test_the_part_lines_remain_on_the_cancelled_job(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $item = $this->items()->addPart($job, $this->stockedPart(10), $actor, ['quantity' => 2]);

        $this->service()->cancel($job, $actor);

        $this->assertSame(JobStatus::Cancelled, $job->fresh()->status);
        $this->assertNotNull($item->fresh());
        $this->assertSame('2.00', $item->fresh()->quantity);
    }

    public function test_labor_lines_are_untouched_by_a_cancellation(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $labor = JobItem::factory()->forJob($job)->create(['description' => 'Brake service']);
        $this->items()->addPart($job, $this->stockedPart(10), $actor, ['quantity' => 2]);

        $this->service()->cancel($job, $actor);

        $this->assertSame('Brake service', $labor->fresh()->description);
        $this->assertSame(1, StockMovement::query()->where('reason', StockMovementReason::JobReturn)->count());
    }

    public function test_a_job_with_no_part_lines_cancels_and_writes_no_movement(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create();

        $this->service()->cancel($job, $this->actor());

        $this->assertSame(JobStatus::Cancelled, $job->fresh()->status);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_a_second_sequential_cancellation_throws_and_writes_no_second_set(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(10);

        $this->items()->addPart($job, $part, $actor, ['quantity' => 3]);
        $this->service()->cancel($job, $actor);

        $movementsAfterFirst = StockMovement::query()->count();

        try {
            $this->service()->cancel($job, $actor);
            $this->fail('Expected the second cancellation to be refused.');
        } catch (InvalidJobTransition $exception) {
            $this->assertSame('cancel', $exception->transition);
        }

        $this->assertSame($movementsAfterFirst, StockMovement::query()->count());
        $this->assertSame(10, $part->fresh()->quantity_in_stock);
        $this->assertStockMatchesLedger();
    }

    /**
     * The second caller holds a stale instance that still reads in_progress, so it
     * passes the pre-flight guard and is refused only by the re-check under the row
     * lock. That is the mechanism the spec relies on; genuine concurrency is NOT
     * exercised here — LazilyRefreshDatabase wraps the test in a transaction, so the
     * service's own transaction is a savepoint and no second connection contends.
     */
    public function test_a_stale_second_canceller_is_refused_under_the_lock_and_returns_nothing(): void
    {
        $actor = $this->actor();
        $first = Job::factory()->inProgress()->create();
        $part = $this->stockedPart(10);

        $this->items()->addPart($first, $part, $actor, ['quantity' => 3]);

        $second = Job::findOrFail($first->getKey());

        $this->service()->cancel($first, $actor);
        $movementsAfterFirst = StockMovement::query()->count();

        $this->expectException(InvalidJobTransition::class);

        try {
            $this->service()->cancel($second, $actor);
        } finally {
            $this->assertSame($movementsAfterFirst, StockMovement::query()->count());
            $this->assertSame(10, $part->fresh()->quantity_in_stock);
            $this->assertStockMatchesLedger();
        }
    }

    public function test_the_cancellation_locks_the_job_row_before_returning_stock(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $this->items()->addPart($job, $this->stockedPart(10), $actor, ['quantity' => 2]);

        DB::enableQueryLog();
        $this->service()->cancel($job, $actor);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lockIndex = $this->firstIndexMatching($queries, fn (string $q): bool => str_contains($q, 'from `jobs`') && str_contains($q, 'for update'));
        $movementIndex = $this->firstIndexMatching($queries, fn (string $q): bool => str_contains($q, 'insert into `stock_movements`'));

        $this->assertNotNull($lockIndex, 'No locking read of the job row was issued.');
        $this->assertNotNull($movementIndex, 'No stock movement was inserted.');
        $this->assertLessThan($movementIndex, $lockIndex);
    }

    public function test_a_cancelled_jobs_part_line_can_no_longer_be_deleted_for_a_second_return(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $item = $this->items()->addPart($job, $this->stockedPart(10), $actor, ['quantity' => 2]);

        $this->service()->cancel($job, $actor);

        $movementsAfterCancel = StockMovement::query()->count();

        $this->actingAs($actor)
            ->delete(route('job-items.destroy', $item))
            ->assertForbidden();

        $this->assertNotNull($item->fresh());
        $this->assertSame($movementsAfterCancel, StockMovement::query()->count());
        $this->assertStockMatchesLedger();
    }

    public function test_the_cancel_confirmation_names_how_many_part_lines_return_to_stock(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();
        $job->refresh();

        $this->assertSame(0, $job->partLineCount());

        $this->items()->addPart($job, $this->stockedPart(10), $actor, ['quantity' => 1]);

        $single = $this->cancelConfirmation($job, $actor);

        $this->items()->addPart($job, $this->stockedPart(10), $actor, ['quantity' => 1]);

        $plural = $this->cancelConfirmation($job, $actor);

        $this->assertStringContainsString('One part line will be returned to stock', $single);
        $this->assertStringContainsString('2 part lines will be returned to stock', $plural);
        $this->assertStringNotContainsString('part line', $this->cancelConfirmation(
            Job::factory()->inProgress()->create(),
            $actor,
        ));
    }

    public function test_part_line_count_reads_the_loaded_collection_without_a_second_query(): void
    {
        $actor = $this->actor();
        $job = Job::factory()->inProgress()->create();

        $this->items()->addPart($job, $this->stockedPart(10), $actor, ['quantity' => 1]);
        JobItem::factory()->forJob($job)->create();

        $loaded = Job::query()->with('items')->findOrFail($job->getKey());

        DB::enableQueryLog();
        $count = $loaded->partLineCount();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(1, $count);
        $this->assertSame([], $queries);
        $this->assertSame(1, Job::query()->findOrFail($job->getKey())->partLineCount());
    }

    private function cancelConfirmation(Job $job, User $actor): string
    {
        $html = $this->actingAs($actor)->get(route('jobs.show', $job))->assertOk()->getContent();

        preg_match('/data-confirm="([^"]*)"/', $html, $match);

        $this->assertNotEmpty($match, 'No confirmation was rendered on the cancel control.');

        return html_entity_decode($match[1]);
    }

    /**
     * @param  list<string>  $queries
     * @param  callable(string): bool  $matches
     */
    private function firstIndexMatching(array $queries, callable $matches): ?int
    {
        foreach ($queries as $index => $query) {
            if ($matches($query)) {
                return $index;
            }
        }

        return null;
    }

    private function stockedPart(int $quantity): Part
    {
        $part = Part::factory()->create();

        app(StockService::class)->record($part, $quantity, StockMovementReason::Restock, $this->actor());

        return $part;
    }

    private function actor(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function items(): JobItemService
    {
        return app(JobItemService::class);
    }
}
