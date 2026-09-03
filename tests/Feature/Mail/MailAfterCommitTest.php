<?php

namespace Tests\Feature\Mail;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\CustomerMailer;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoiceService;
use App\Services\JobService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * These assertions deliberately use NO fake. Both Mail::fake() and Queue::fake()
 * record immediately and never consult `db.transactions`, so under either one a
 * rolled-back transaction still records a queued mailable — the assertion would
 * pass while proving the opposite of the intent.
 *
 * This works inside the test transaction because RefreshDatabase installs
 * Illuminate\Foundation\Testing\DatabaseTransactionsManager, whose
 * afterCommitCallbacksShouldBeExecuted() is $level === 1 rather than 0, so the
 * service's own transaction is treated as the root.
 */
class MailAfterCommitTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
    }

    private function completableJob(): Job
    {
        $job = Job::factory()->inProgress()->create();

        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return $job;
    }

    private function queuedCount(): int
    {
        return DB::table('queue_jobs')->count();
    }

    public function test_completing_a_job_leaves_exactly_one_queued_job(): void
    {
        app(JobService::class)->complete($this->completableJob());

        $this->assertSame(1, $this->queuedCount());
    }

    public function test_issuing_an_invoice_leaves_exactly_one_queued_job(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        $this->assertSame(1, $this->queuedCount());
    }

    /**
     * The discriminating assertion for $afterCommit on the `database` driver.
     *
     * Asserting the row count AFTER a rollback proves nothing here: the
     * `queue_jobs` INSERT is itself inside the same database transaction, so it
     * is rolled back with everything else whether the send was deferred or not.
     * That assertion passes with $afterCommit = false — verified by mutation.
     *
     * What only deferral can produce is the row being ABSENT while the
     * surrounding transaction is still open.
     */
    public function test_the_queued_job_is_not_written_until_the_surrounding_transaction_commits(): void
    {
        $job = $this->completableJob();

        DB::transaction(function () use ($job): void {
            app(JobService::class)->complete($job);

            $this->assertSame(0, $this->queuedCount(), 'the send was not held until commit');
        });

        $this->assertSame(1, $this->queuedCount());
    }

    /**
     * An end-state guard, NOT a proof of $afterCommit — deleting the property
     * leaves this green, because the immediate INSERT would be rolled back with
     * the rest of the transaction. What it does catch is the send escaping to a
     * queue store outside the database, where a rollback could not reclaim it.
     * The property itself is pinned by the test above.
     */
    public function test_a_rolled_back_transaction_queues_nothing(): void
    {
        $job = $this->completableJob();

        try {
            DB::transaction(function () use ($job): void {
                app(JobService::class)->complete($job);

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
            // The rollback is the subject of the test.
        }

        $this->assertSame(0, $this->queuedCount());
        $this->assertNull($job->fresh()->completed_at);
    }

    public function test_an_invoice_number_collision_rolls_back_its_attempt_and_queues_one_email(): void
    {
        $taken = Invoice::factory()->create()->number;

        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $generator = new CollidingNumberGeneratorForMail($taken, 1);

        $service = new InvoiceService($generator, app(CustomerMailer::class));
        $service->issue($job, User::factory()->receptionist()->create());

        $this->assertSame(2, $generator->calls);
        $this->assertSame(1, $this->queuedCount());
    }
}

class CollidingNumberGeneratorForMail extends InvoiceNumberGenerator
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
