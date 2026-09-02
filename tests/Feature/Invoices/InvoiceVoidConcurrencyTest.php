<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceTransition;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Voiding writes two rows, so it locks two rows: two admins voiding the same
 * invoice at once must produce one void, and neither may clear a lock a third
 * party is concurrently setting.
 *
 * REAL CONTENTION IS NOT TESTED HERE, and cannot be: LazilyRefreshDatabase wraps
 * each test in a transaction, so the service's own transaction is a savepoint
 * and no second connection can contend for either row. Specs 15, 16, 17 and 18
 * all hit this wall and reported it rather than faking it. What is proven here
 * is the mechanism — both locking reads are issued, both precede the writes, the
 * job is read with every column, a sequential second void is refused, and the
 * job update carries exactly one column.
 */
class InvoiceVoidConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function issued(): Invoice
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    /**
     * @return list<string>
     */
    private function queriesWhileVoiding(Invoice $invoice): array
    {
        DB::enableQueryLog();
        app(InvoiceService::class)->void($invoice, User::factory()->admin()->create());
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }

    private function firstIndexMatching(array $queries, callable $matcher, string $what): int
    {
        foreach ($queries as $index => $query) {
            if ($matcher($query)) {
                return $index;
            }
        }

        $this->fail("No {$what} was issued:\n".implode("\n", $queries));
    }

    public function test_both_the_invoice_row_and_the_job_row_are_locked_for_update(): void
    {
        $queries = $this->queriesWhileVoiding($this->issued());

        $invoiceLocks = array_filter(
            $queries,
            fn (string $q): bool => str_contains($q, 'from `invoices`') && str_contains($q, 'for update'),
        );

        $jobLocks = array_filter(
            $queries,
            fn (string $q): bool => str_contains($q, 'from `jobs`') && str_contains($q, 'for update'),
        );

        $this->assertNotEmpty($invoiceLocks, "The invoice row was never locked:\n".implode("\n", $queries));
        $this->assertNotEmpty($jobLocks, "The job row was never locked:\n".implode("\n", $queries));
    }

    public function test_both_locking_reads_precede_both_writes(): void
    {
        $queries = $this->queriesWhileVoiding($this->issued());

        $invoiceLock = $this->firstIndexMatching(
            $queries,
            fn (string $q): bool => str_contains($q, 'from `invoices`') && str_contains($q, 'for update'),
            'locking read of the invoice row',
        );

        $jobLock = $this->firstIndexMatching(
            $queries,
            fn (string $q): bool => str_contains($q, 'from `jobs`') && str_contains($q, 'for update'),
            'locking read of the job row',
        );

        $invoiceWrite = $this->firstIndexMatching(
            $queries,
            fn (string $q): bool => str_starts_with($q, 'update `invoices`'),
            'write to the invoice row',
        );

        $jobWrite = $this->firstIndexMatching(
            $queries,
            fn (string $q): bool => str_starts_with($q, 'update `jobs`'),
            'write to the job row',
        );

        $this->assertLessThan($invoiceWrite, $invoiceLock);
        $this->assertLessThan($jobWrite, $jobLock);
        $this->assertLessThan($jobWrite, $invoiceLock, 'Both rows are held before either is written.');
    }

    /**
     * Job's guard fails closed when an existing row's raw originals lack
     * locked_at, so a partial select would throw JobLockedException with an
     * empty change list and no obvious cause.
     */
    public function test_the_job_is_read_with_every_column(): void
    {
        $queries = $this->queriesWhileVoiding($this->issued());

        $index = $this->firstIndexMatching(
            $queries,
            fn (string $q): bool => str_contains($q, 'from `jobs`') && str_contains($q, 'for update'),
            'locking read of the job row',
        );

        $this->assertStringStartsWith('select * from `jobs`', $queries[$index]);
    }

    /**
     * The whole feature hinges on this. Spec 08's guard permits a change to
     * locked_at and NOTHING else while the job is locked, so a void that also
     * set some other job attribute would be refused — correctly. Eloquent
     * refreshes updated_at inside performUpdate(), after the saving event has
     * already run, which is why the timestamp does not count against it.
     */
    public function test_the_job_update_sets_locked_at_and_nothing_else(): void
    {
        $queries = $this->queriesWhileVoiding($this->issued());

        $index = $this->firstIndexMatching(
            $queries,
            fn (string $q): bool => str_starts_with($q, 'update `jobs`'),
            'write to the job row',
        );

        preg_match('/update `jobs` set (.*?) where /', $queries[$index], $matches);

        $this->assertNotEmpty($matches, "Could not read the set clause from:\n".$queries[$index]);

        $columns = array_map(
            fn (string $assignment): string => trim(explode('=', $assignment)[0]),
            explode(',', $matches[1]),
        );

        $this->assertSame(
            ['`locked_at`', '`jobs`.`updated_at`'],
            $columns,
            'Any third column would be refused by the lock guard.',
        );
    }

    public function test_a_sequential_second_void_is_refused_and_changes_nothing(): void
    {
        $invoice = $this->issued();
        $service = app(InvoiceService::class);

        $service->void($invoice, User::factory()->admin()->create());

        $after = $invoice->fresh();

        $this->expectException(InvalidInvoiceTransition::class);

        try {
            $service->void($invoice->fresh(), User::factory()->admin()->create());
        } finally {
            $latest = $invoice->fresh();

            $this->assertSame(InvoiceStatus::Void, $latest->status);
            $this->assertSame($after->voided_by, $latest->voided_by);
            $this->assertTrue($after->voided_at->equalTo($latest->voided_at));
            $this->assertSame(1, Invoice::query()->count());
        }
    }
}
