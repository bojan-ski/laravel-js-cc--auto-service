<?php

namespace Tests\Feature\Mail;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ThrowingMailTransport;
use Tests\TestCase;

/**
 * Forces the `database` driver deliberately. Under the test environment's
 * default `sync` driver SyncQueue::executeJob() rethrows, and because the send
 * is deferred to commit the throw escapes Connection::commit() and surfaces as a
 * 500 — the opposite of the property being asserted here.
 *
 * #[Tries(3)] means one worker cycle RELEASES the job rather than failing it, so
 * `failed_jobs` stays empty and the queue row survives. Retry EXHAUSTION is not
 * covered: it needs a real queue:work loop across the backoff window.
 */
class MailFailureIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'database',
            'mail.default' => 'throwing',
            'mail.mailers.throwing' => ['transport' => 'throwing'],
        ]);

        Mail::extend('throwing', fn () => new ThrowingMailTransport);
    }

    private function workOnce(): void
    {
        Artisan::call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--no-interaction' => true,
        ]);
    }

    public function test_a_throwing_mailable_leaves_the_job_completed(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        app(JobService::class)->complete($job);

        $this->assertSame(JobStatus::Completed, $job->fresh()->status);

        $this->workOnce();

        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_a_throwing_mailable_leaves_the_invoice_issued_and_unpaid(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        $this->workOnce();

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
        $this->assertNotNull($job->fresh()->locked_at);
    }

    /**
     * The criterion is about the STAFF-FACING surface, so it is asserted at the
     * route rather than at the service: the receptionist sees the ordinary
     * success flash and no error, and only then does the send fail on a worker.
     */
    public function test_nothing_about_the_failure_reaches_the_staff_facing_surface(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $response = $this
            ->actingAs(User::factory()->receptionist()->create())
            ->patch(route('jobs.complete', $job));

        $response->assertRedirect(route('jobs.show', $job));
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');
        $response->assertSessionHas('success');

        $this->workOnce();

        $this->assertSame(JobStatus::Completed, $job->fresh()->status);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_the_failed_send_is_released_for_retry_rather_than_failed(): void
    {
        $job = Job::factory()->inProgress()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        app(JobService::class)->complete($job);

        $this->assertSame(0, (int) DB::table('queue_jobs')->value('attempts'));

        $this->workOnce();

        $this->assertSame(1, DB::table('queue_jobs')->count());
        $this->assertSame(1, (int) DB::table('queue_jobs')->value('attempts'));
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }
}
