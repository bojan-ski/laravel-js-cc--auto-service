<?php

namespace Tests\Feature\Jobs;

use App\Enums\StockMovementReason;
use App\Models\Job;
use App\Models\Part;
use App\Models\User;
use App\Services\JobItemService;
use App\Services\JobService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobCancellationPerformanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Exactly the three writes the ledger itself requires per line: lock the part,
     * update its balance, insert the movement. A fourth would mean the part is being
     * resolved again on top of the locked re-read StockService::record() already does.
     *
     * An earlier version of this test asserted linearity instead, which an N+1
     * satisfies just as well (7/11/15 rather than 7/10/13) — it did not catch the
     * defect it was written for.
     */
    public function test_each_additional_part_line_costs_exactly_the_three_ledger_writes(): void
    {
        $this->assertSame(3, $this->cancellationQueries(3) - $this->cancellationQueries(2));
    }

    public function test_a_cancellation_resolves_each_part_exactly_once(): void
    {
        $queries = $this->queriesForCancellation(4);

        $unlockedPartSelects = array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `parts`') && ! str_contains($query, 'for update'),
        );

        $this->assertCount(1, $unlockedPartSelects, implode("\n", $queries));
    }

    private function cancellationQueries(int $lines): int
    {
        return count($this->queriesForCancellation($lines));
    }

    /**
     * @return list<string>
     */
    private function queriesForCancellation(int $lines): array
    {
        $actor = User::factory()->receptionist()->create();
        $job = Job::factory()->inProgress()->create();

        for ($i = 0; $i < $lines; $i++) {
            $part = Part::factory()->create();
            app(StockService::class)->record($part, 10, StockMovementReason::Restock, $actor);
            app(JobItemService::class)->addPart($job, $part, $actor, ['quantity' => 1]);
        }

        DB::enableQueryLog();
        app(JobService::class)->cancel($job, $actor);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $queries;
    }
}
