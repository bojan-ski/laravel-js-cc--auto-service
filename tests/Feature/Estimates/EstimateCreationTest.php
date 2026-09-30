<?php

namespace Tests\Feature\Estimates;

use App\Enums\EstimateStatus;
use App\Enums\JobItemType;
use App\Exceptions\EstimateNotCreatable;
use App\Models\Estimate;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use App\Services\EstimateNumberGenerator;
use App\Services\EstimateService;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use App\Services\JobService;
use Carbon\CarbonInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsStockInvariant;
use Tests\TestCase;

/**
 * REAL CONTENTION IS NOT TESTED HERE, and cannot be: LazilyRefreshDatabase
 * wraps each test in a transaction, so the service's own transaction is a
 * savepoint and no second connection can contend for a row. The mutex is
 * evidenced instead by the locking reads, their order before any write, and
 * the re-checks under them.
 */
class EstimateCreationTest extends TestCase
{
    use AssertsStockInvariant;
    use LazilyRefreshDatabase;

    private function service(): EstimateService
    {
        return app(EstimateService::class);
    }

    private function desk(): User
    {
        return User::factory()->receptionist()->create();
    }

    private function jobWithLines(): Job
    {
        $job = Job::factory()->inProgress()->create();

        JobItem::factory()->forJob($job)->create(['description' => 'Brake service', 'quantity' => '1.50', 'unit_price' => '3000.00']);
        JobItem::factory()->forJob($job)->discounted('12.50')->create(['description' => 'Diagnostics', 'quantity' => '0.50', 'unit_price' => '2999.99']);
        JobItem::factory()->forJob($job)->part()->create(['description' => 'Pad set', 'quantity' => 2, 'unit_price' => '1234.57']);

        return $job;
    }

    public function test_every_line_is_copied_in_id_order_with_its_discount_and_rounded_total(): void
    {
        $job = $this->jobWithLines();

        $estimate = $this->service()->create($job, $this->desk());

        $items = $job->items()->orderBy('id')->get();
        $lines = $estimate->lines()->get();

        $this->assertCount(3, $lines);
        $this->assertSame([1, 2, 3], $lines->pluck('position')->all());

        foreach ($items as $index => $item) {
            $line = $lines[$index];

            $this->assertSame($item->type, $line->type);
            $this->assertSame($item->description, $line->description);
            $this->assertSame($item->quantity, $line->quantity);
            $this->assertSame($item->unit_price, $line->unit_price);
            $this->assertSame($item->discount_percent, $line->discount_percent);
            $this->assertSame($item->lineTotal(), $line->line_total);
        }

        $this->assertSame(JobItemType::Part, $lines[2]->type);
        $this->assertSame('1312.50', $lines[1]->line_total, 'Half-up on 1,312.495625; truncation would give 1,312.49.');
    }

    public function test_the_totals_are_the_invoice_arithmetic_and_an_invoice_from_the_same_lines_agrees(): void
    {
        $job = $this->jobWithLines();
        $expected = app(InvoiceService::class)->totalsFor($job->load('items'));

        $estimate = $this->service()->create($job, $this->desk());

        $this->assertSame($expected['subtotal'], $estimate->subtotal);
        $this->assertSame($expected['discount'], $estimate->discount_total);
        $this->assertSame($expected['tax'], $estimate->tax_amount);
        $this->assertSame($expected['total'], $estimate->total);
        $this->assertSame('20.00', $estimate->fresh()->tax_rate);

        app(JobService::class)->complete($job->fresh());
        $invoice = app(InvoiceService::class)->issue($job->fresh(), $this->desk());
        $stored = $estimate->fresh();

        foreach (['subtotal', 'discount_total', 'tax_rate', 'tax_amount', 'total'] as $column) {
            $this->assertSame($invoice->fresh()->{$column}, $stored->{$column}, $column);
        }
    }

    public function test_the_rate_is_captured_from_config_at_creation(): void
    {
        config(['shop.vat_rate' => '17.50']);

        $estimate = $this->service()->create($this->jobWithLines(), $this->desk());

        $this->assertSame('17.50', $estimate->fresh()->tax_rate);
        $this->assertSame('17.5', $estimate->fresh()->taxRateLabel());
    }

    public function test_a_new_estimate_is_pending_and_records_its_creator(): void
    {
        $desk = $this->desk();

        $estimate = $this->service()->create($this->jobWithLines(), $desk)->fresh();

        $this->assertTrue($estimate->isPending());
        $this->assertSame($desk->getKey(), $estimate->created_by);
        $this->assertNull($estimate->decided_at);
        $this->assertNull($estimate->decision_method);
        $this->assertNull($estimate->superseded_at);
        $this->assertNull($estimate->emailed_at);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function openStates(): array
    {
        return ['pending' => ['pending'], 'approved' => ['approved']];
    }

    #[DataProvider('openStates')]
    public function test_creating_supersedes_the_open_estimate(string $state): void
    {
        $job = $this->jobWithLines();
        $open = Estimate::factory()->{$state}()->forJob($job)->create();

        $this->freezeSecond();
        $revision = $this->service()->create($job, $this->desk());

        $open->refresh();
        $this->assertTrue($open->isSuperseded());
        $this->assertEquals(now(), $open->superseded_at);
        $this->assertTrue($revision->isPending());
        $this->assertSame(1, $job->estimates()->whereIn('status', EstimateStatus::open())->count());
    }

    public function test_a_declined_estimate_is_left_declined(): void
    {
        $job = $this->jobWithLines();
        $declined = Estimate::factory()->declined()->forJob($job)->create();

        $this->service()->create($job, $this->desk());

        $this->assertTrue($declined->fresh()->isDeclined());
        $this->assertNull($declined->fresh()->superseded_at);
    }

    public function test_three_revisions_leave_one_open_estimate_and_two_superseded(): void
    {
        $job = $this->jobWithLines();

        $this->service()->create($job, $this->desk());
        $this->service()->create($job, $this->desk());
        $last = $this->service()->create($job, $this->desk());

        $this->assertSame(1, $job->estimates()->whereIn('status', EstimateStatus::open())->count());
        $this->assertSame(2, $job->estimates()->where('status', EstimateStatus::Superseded)->count());
        $this->assertTrue($job->currentEstimate()->first()->is($last));
    }

    public function test_changing_the_job_lines_afterwards_changes_nothing_on_the_estimate(): void
    {
        $job = $this->jobWithLines();
        $estimate = $this->service()->create($job, $this->desk());
        $snapshot = $estimate->fresh()->getAttributes();
        $lineSnapshot = $estimate->lines()->get()->map->getAttributes()->all();

        $items = app(JobItemService::class);
        $first = $job->items()->orderBy('id')->first();
        $items->update($first, $this->desk(), ['description' => 'Changed', 'quantity' => '9.00', 'user_id' => $first->user_id]);
        $items->delete($job->items()->orderByDesc('id')->first(), $this->desk());
        JobItem::factory()->forJob($job)->create();

        $this->assertSame($snapshot, $estimate->fresh()->getAttributes());
        $this->assertSame($lineSnapshot, $estimate->lines()->get()->map->getAttributes()->all());
    }

    public function test_creating_writes_nothing_to_the_job_its_lines_or_stock(): void
    {
        $job = Job::factory()->inProgress()->create();
        $part = Part::factory()->create();
        app(JobItemService::class)->addPart($job, $part, $this->desk(), ['quantity' => 2]);
        $jobBefore = $job->fresh()->getAttributes();
        $itemsBefore = $job->items()->get()->map->getAttributes()->all();
        $stockBefore = $part->fresh()->quantity_in_stock;
        $movementsBefore = DB::table('stock_movements')->count();

        $this->service()->create($job, $this->desk());

        $this->assertSame($jobBefore, $job->fresh()->getAttributes());
        $this->assertSame($itemsBefore, $job->items()->get()->map->getAttributes()->all());
        $this->assertSame($stockBefore, $part->fresh()->quantity_in_stock);
        $this->assertSame($movementsBefore, DB::table('stock_movements')->count());
        $this->assertStockMatchesLedger();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusals(): array
    {
        return [
            'completed' => ['completed', EstimateNotCreatable::NOT_WORKING],
            'cancelled' => ['cancelled', EstimateNotCreatable::NOT_WORKING],
            'lineless' => ['lineless', EstimateNotCreatable::NO_LINE_ITEMS],
        ];
    }

    #[DataProvider('refusals')]
    public function test_a_job_that_cannot_be_estimated_is_refused_with_its_reason(string $state, string $reason): void
    {
        $job = match ($state) {
            'lineless' => Job::factory()->inProgress()->create(),
            default => tap(Job::factory()->{$state}()->create(), fn (Job $job) => JobItem::factory()->forJob($job)->create()),
        };

        $this->assertRefused($job, $reason);
    }

    public function test_a_locked_working_job_is_refused_as_locked(): void
    {
        $job = $this->jobWithLines();
        DB::table('jobs')->where('id', $job->getKey())->update(['locked_at' => now()]);

        $this->assertRefused($job, EstimateNotCreatable::LOCKED);
    }

    public function test_the_refusal_reads_the_locked_row_not_the_callers_stale_copy(): void
    {
        $job = $this->jobWithLines();
        DB::table('jobs')->where('id', $job->getKey())->update(['status' => 'cancelled']);

        $this->assertTrue($job->isWorking(), 'The caller still holds the stale working copy.');
        $this->assertRefused($job, EstimateNotCreatable::NOT_WORKING);
    }

    public function test_numbers_follow_the_year_and_restart_each_january(): void
    {
        $this->travelTo('2026-12-31 15:00:00');
        $this->assertSame('EST-2026-0001', $this->service()->create($this->jobWithLines(), $this->desk())->number);
        $this->assertSame('EST-2026-0002', $this->service()->create($this->jobWithLines(), $this->desk())->number);

        $this->travelTo('2027-01-02 09:00:00');
        $this->assertSame('EST-2027-0001', $this->service()->create($this->jobWithLines(), $this->desk())->number);
    }

    public function test_gaps_are_never_back_filled(): void
    {
        $this->travelTo('2026-03-04 09:00:00');
        Estimate::factory()->create(['number' => 'EST-2026-0007']);

        $this->assertSame('EST-2026-0008', $this->service()->create($this->jobWithLines(), $this->desk())->number);
    }

    public function test_a_number_collision_is_recovered_by_regenerating(): void
    {
        $taken = 'EST-'.now()->format('Y').'-0001';
        Estimate::factory()->create(['number' => $taken]);
        $generator = new CollidingEstimateNumberGenerator($taken, collidingCalls: 1);
        $this->app->instance(EstimateNumberGenerator::class, $generator);

        $estimate = $this->service()->create($this->jobWithLines(), $this->desk());

        $this->assertSame(2, $generator->calls, 'One collision, then one fresh allocation.');
        $this->assertSame('EST-'.now()->format('Y').'-0002', $estimate->number);
    }

    public function test_the_retry_gives_up_after_three_attempts_and_supersedes_nothing(): void
    {
        $job = $this->jobWithLines();
        $open = Estimate::factory()->forJob($job)->create(['number' => 'EST-'.now()->format('Y').'-0001']);
        $generator = new CollidingEstimateNumberGenerator($open->number, collidingCalls: PHP_INT_MAX);
        $this->app->instance(EstimateNumberGenerator::class, $generator);

        try {
            $this->service()->create($job, $this->desk());
            $this->fail('Expected UniqueConstraintViolationException.');
        } catch (UniqueConstraintViolationException) {
            // The literal, not the constant: asserting against NUMBER_ATTEMPTS
            // would pass for any bound, including one.
            $this->assertSame(3, $generator->calls);
        }

        $this->assertTrue($open->fresh()->isPending(), 'The rolled-back attempt must not leave the open estimate superseded.');
        $this->assertSame(1, $job->estimates()->count());
    }

    public function test_the_job_row_then_the_open_estimate_are_locked_before_any_write(): void
    {
        $job = $this->jobWithLines();
        Estimate::factory()->forJob($job)->create();
        $desk = $this->desk();

        DB::enableQueryLog();
        $this->service()->create($job, $desk);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $jobLock = $this->firstIndex($queries, fn (string $sql): bool => str_contains($sql, 'from `jobs`') && str_contains($sql, 'for update'));
        $estimateLock = $this->firstIndex($queries, fn (string $sql): bool => str_contains($sql, 'from `estimates`') && str_contains($sql, 'for update'));
        $firstWrite = $this->firstIndex($queries, fn (string $sql): bool => str_starts_with($sql, 'update `estimates`') || str_starts_with($sql, 'insert into `estimate'));

        $this->assertNotNull($jobLock, "No locking read of the job:\n".implode("\n", $queries));
        $this->assertNotNull($estimateLock, "No locking read of the open estimate:\n".implode("\n", $queries));
        $this->assertNotNull($firstWrite);
        $this->assertLessThan($estimateLock, $jobLock, 'The job row is locked first.');
        $this->assertLessThan($firstWrite, $estimateLock, 'Both locks precede the first write.');
    }

    /**
     * A locking read that matches no row takes an InnoDB gap lock, and two
     * first estimates on different jobs would then deadlock on each other's
     * insert. With no open estimate there must be no locking read of the
     * estimates table at all; the job row lock alone serializes creates.
     */
    public function test_a_job_without_an_open_estimate_takes_no_locking_read_of_the_estimates_table(): void
    {
        $job = $this->jobWithLines();
        Estimate::factory()->declined()->forJob($job)->create();
        $desk = $this->desk();

        DB::enableQueryLog();
        $this->service()->create($job, $desk);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertNotNull($this->firstIndex($queries, fn (string $sql): bool => str_contains($sql, 'from `estimates`')), 'The open estimate was never looked up.');
        $this->assertNull(
            $this->firstIndex($queries, fn (string $sql): bool => str_contains($sql, 'from `estimates`') && str_contains($sql, 'for update')),
            "A locking read of estimates matched no row:\n".implode("\n", $queries),
        );
    }

    public function test_the_open_estimate_is_locked_by_its_primary_key(): void
    {
        $job = $this->jobWithLines();
        $open = Estimate::factory()->forJob($job)->create();
        $desk = $this->desk();

        DB::enableQueryLog();
        $this->service()->create($job, $desk);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $locking = array_values(array_filter($log, fn (array $entry): bool => str_contains($entry['query'], 'from `estimates`') && str_contains($entry['query'], 'for update')));

        $this->assertCount(1, $locking);
        $this->assertStringContainsString('`estimates`.`id` = ?', $locking[0]['query']);
        $this->assertStringNotContainsString('`job_id`', $locking[0]['query']);
        $this->assertSame([$open->getKey()], $locking[0]['bindings']);
    }

    public function test_an_estimate_declined_after_the_lookup_is_left_declined(): void
    {
        $job = $this->jobWithLines();
        $open = Estimate::factory()->forJob($job)->create();

        $declined = false;

        DB::listen(function (QueryExecuted $query) use ($open, &$declined): void {
            $isLookup = str_starts_with($query->sql, 'select `id` from `estimates`') && ! str_contains($query->sql, 'for update');

            if (! $declined && $isLookup) {
                $declined = true;
                DB::table('estimates')->where('id', $open->getKey())->update(['status' => 'declined']);
            }
        });

        $this->service()->create($job, $this->desk());

        $this->assertTrue($declined, 'The lookup query was never seen, so the race was not staged.');
        $this->assertTrue($open->fresh()->isDeclined(), 'The locked re-read must see the decline and leave it.');
        $this->assertNull($open->fresh()->superseded_at);
    }

    /**
     * @param  list<string>  $queries
     */
    private function firstIndex(array $queries, callable $matches): ?int
    {
        foreach ($queries as $index => $query) {
            if ($matches($query)) {
                return $index;
            }
        }

        return null;
    }

    private function assertRefused(Job $job, string $reason): void
    {
        try {
            $this->service()->create($job, $this->desk());
            $this->fail("Expected EstimateNotCreatable [{$reason}].");
        } catch (EstimateNotCreatable $exception) {
            $this->assertSame($reason, $exception->reason);
            $this->assertTrue($exception->job->is($job));
        }

        $this->assertSame(0, Estimate::query()->count(), 'A refusal writes no estimate.');
    }
}

class CollidingEstimateNumberGenerator extends EstimateNumberGenerator
{
    public int $calls = 0;

    public function __construct(
        private readonly string $taken,
        private readonly int $collidingCalls,
    ) {}

    public function generate(CarbonInterface $on): string
    {
        $this->calls++;

        return $this->calls <= $this->collidingCalls ? $this->taken : parent::generate($on);
    }
}
