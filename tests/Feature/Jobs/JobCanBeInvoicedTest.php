<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobStatus;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JobCanBeInvoicedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_completed_unlocked_job_with_a_line_item_and_no_invoice_can_be_invoiced(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        $this->assertTrue($job->canBeInvoiced());
    }

    /**
     * @return array<string, array{JobStatus}>
     */
    public static function nonCompletedStatuses(): array
    {
        return [
            'received' => [JobStatus::Received],
            'in progress' => [JobStatus::InProgress],
            'awaiting parts' => [JobStatus::AwaitingParts],
            'cancelled' => [JobStatus::Cancelled],
        ];
    }

    #[DataProvider('nonCompletedStatuses')]
    public function test_only_a_completed_job_can_be_invoiced(JobStatus $status): void
    {
        $job = Job::factory()->create(['status' => $status]);
        JobItem::factory()->forJob($job)->create();

        $this->assertFalse($job->canBeInvoiced());
    }

    public function test_a_job_with_no_line_items_cannot_be_invoiced(): void
    {
        $this->assertFalse(Job::factory()->completed()->create()->canBeInvoiced());
    }

    public function test_a_locked_job_cannot_be_invoiced(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();

        $job->locked_at = now();
        $job->save();

        $this->assertFalse($job->canBeInvoiced());
    }

    public function test_a_job_holding_an_unpaid_invoice_cannot_be_invoiced(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();
        Invoice::factory()->unpaid()->forJob($job)->create();

        $this->assertTrue($job->hasActiveInvoice());
        $this->assertFalse($job->canBeInvoiced());
    }

    public function test_a_job_holding_a_paid_invoice_cannot_be_invoiced(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();
        Invoice::factory()->paid()->forJob($job)->create();

        $this->assertFalse($job->canBeInvoiced());
    }

    public function test_a_job_holding_only_void_invoices_can_be_invoiced(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create();
        Invoice::factory()->void()->forJob($job)->create();

        $this->assertFalse($job->hasActiveInvoice());
        $this->assertTrue($job->canBeInvoiced());
    }

    public function test_the_active_invoice_relation_ignores_void_rows(): void
    {
        $job = Job::factory()->locked()->create();

        Invoice::factory()->void()->forJob($job)->create();
        $active = Invoice::factory()->unpaid()->forJob($job)->create();
        Invoice::factory()->void()->forJob($job)->create();

        $this->assertTrue($job->activeInvoice()->first()->is($active));
        $this->assertSame(3, $job->invoices()->count());
    }

    public function test_the_active_invoice_relation_is_empty_when_every_invoice_is_void(): void
    {
        $job = Job::factory()->locked()->create();
        Invoice::factory()->void()->forJob($job)->create();

        $this->assertNull($job->activeInvoice()->first());
    }

    /**
     * The predicate is ordered so the in-memory checks short-circuit first: a
     * working job must reach neither the line-item nor the invoice query.
     */
    public function test_a_working_job_short_circuits_before_any_query(): void
    {
        $job = Job::factory()->inProgress()->create();

        DB::enableQueryLog();
        $this->assertFalse($job->canBeInvoiced());
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertEmpty($queries, "The status check must short-circuit:\n".implode("\n", $queries));
    }
}
