<?php

namespace Tests\Feature\Invoices;

use App\Exceptions\InvoiceNotIssuable;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The job row lock is the mutex enforcing "at most one non-void invoice per
 * job" — no database constraint can express it, because the rule is partial and
 * MySQL has no partial unique index.
 *
 * REAL CONTENTION IS NOT TESTED HERE, and cannot be: LazilyRefreshDatabase wraps
 * each test in a transaction, so the service's own transaction is a savepoint
 * and no second connection can contend for the row. The mechanism is instead
 * evidenced three ways — the locking read is issued, it precedes the invoice
 * insert, and a sequential second attempt is refused and writes nothing. Specs
 * 15 and 16 hit the same wall and reported it rather than faking it.
 */
class InvoiceConcurrencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function invoiceableJob(): Job
    {
        $job = Job::factory()->completed()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 100]);

        return $job;
    }

    /**
     * @return list<string>
     */
    private function queriesWhileIssuing(Job $job): array
    {
        DB::enableQueryLog();
        app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }

    public function test_the_job_row_is_locked_for_update_inside_the_transaction(): void
    {
        $queries = $this->queriesWhileIssuing($this->invoiceableJob());

        $locking = array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `jobs`') && str_contains($query, 'for update'),
        );

        $this->assertNotEmpty($locking, "No locking read of the job row was issued:\n".implode("\n", $queries));
    }

    public function test_the_locking_read_precedes_the_invoice_insert(): void
    {
        $queries = $this->queriesWhileIssuing($this->invoiceableJob());

        $lockIndex = null;
        $insertIndex = null;

        foreach ($queries as $index => $query) {
            if ($lockIndex === null && str_contains($query, 'from `jobs`') && str_contains($query, 'for update')) {
                $lockIndex = $index;
            }

            if ($insertIndex === null && str_contains($query, 'insert into `invoices`')) {
                $insertIndex = $index;
            }
        }

        $this->assertNotNull($lockIndex, "No locking read:\n".implode("\n", $queries));
        $this->assertNotNull($insertIndex, "No invoice insert:\n".implode("\n", $queries));
        $this->assertLessThan(
            $insertIndex,
            $lockIndex,
            "The job row must be locked before the invoice is written:\n".implode("\n", $queries),
        );
    }

    public function test_the_locking_read_selects_every_column(): void
    {
        $queries = $this->queriesWhileIssuing($this->invoiceableJob());

        $locking = array_values(array_filter(
            $queries,
            fn (string $query): bool => str_contains($query, 'from `jobs`') && str_contains($query, 'for update'),
        ));

        $this->assertStringContainsString(
            'select * from `jobs`',
            $locking[0],
            "A partial select would trip the job's fail-closed lock guard.",
        );
    }

    public function test_a_sequential_second_attempt_is_refused_and_writes_nothing(): void
    {
        $job = $this->invoiceableJob();
        $service = app(InvoiceService::class);

        $first = $service->issue($job, User::factory()->receptionist()->create());

        $stale = Job::query()->findOrFail($job->getKey());
        $stale->setRawAttributes([...$stale->getAttributes(), 'locked_at' => null], true);

        try {
            $service->issue($stale, User::factory()->receptionist()->create());
            $this->fail('Expected InvoiceNotIssuable.');
        } catch (InvoiceNotIssuable $exception) {
            $this->assertSame(InvoiceNotIssuable::LOCKED, $exception->precondition);
        }

        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame($first->getKey(), Invoice::query()->sole()->getKey());
    }
}
