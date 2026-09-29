<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\JobLockedException;
use App\Exceptions\JobNotAcceptingLineItems;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use App\Services\JobService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The round trip spec 17 made impossible and this feature restores: a job that
 * is invoiced, locked, corrected and invoiced again.
 */
class InvoiceVoidCycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Job $job;

    private JobItem $line;

    private User $admin;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->receptionist = User::factory()->receptionist()->create();

        $this->job = Job::factory()->completed()->create();
        $this->line = JobItem::factory()->forJob($this->job)->create([
            'quantity' => 1,
            'unit_price' => 1000,
        ]);
    }

    private function invoices(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    public function test_the_full_cycle_issues_locks_voids_unlocks_reopens_and_reissues(): void
    {
        $first = $this->invoices()->issue($this->job, $this->receptionist);

        $this->assertNotNull($this->job->fresh()->locked_at, 'Issuing locks the job.');
        $this->assertSame('1000.00', $first->subtotal);

        $this->invoices()->void($first, $this->admin);

        $this->assertNull($this->job->fresh()->locked_at, 'Voiding unlocks the job.');
        $this->assertSame(JobStatus::Completed, $this->job->fresh()->status);

        app(JobService::class)->reopen($this->job->fresh());

        $this->assertSame(JobStatus::InProgress, $this->job->fresh()->status);

        app(JobItemService::class)->update($this->line->fresh(), $this->receptionist, [
            'description' => $this->line->description,
            'quantity' => '3.00',
            'unit_price' => '1000.00',
            'user_id' => $this->line->user_id,
        ]);

        app(JobService::class)->complete($this->job->fresh());

        $second = $this->invoices()->issue($this->job->fresh(), $this->receptionist);

        $this->assertNotSame($first->number, $second->number, 'A re-issue never reuses a spent number.');
        $this->assertNotSame($first->total, $second->total, 'The new invoice bills the corrected lines.');
        $this->assertSame('3000.00', $second->subtotal);
        $this->assertNotNull($this->job->fresh()->locked_at, 'Re-issuing locks the job again.');

        $this->assertSame(InvoiceStatus::Void, $first->fresh()->status);
        $this->assertSame(InvoiceStatus::Unpaid, $second->fresh()->status);
    }

    public function test_a_reissued_invoice_starts_without_the_voided_ones_payment_method(): void
    {
        $first = $this->invoices()->markPaid(
            $this->invoices()->issue($this->job, $this->receptionist),
            PaymentMethod::Card,
        );

        $this->invoices()->void($first, $this->admin);
        app(JobService::class)->reopen($this->job->fresh());
        app(JobService::class)->complete($this->job->fresh());

        $second = $this->invoices()->issue($this->job->fresh(), $this->receptionist);

        $this->assertNull($second->fresh()->payment_method);
        $this->assertNull($second->fresh()->paid_at);
        $this->assertSame(PaymentMethod::Card, $first->fresh()->payment_method, 'The void invoice keeps its own record.');
    }

    /**
     * The distinction this spec makes reachable for the first time. Before it,
     * every line-item refusal on an invoiced job was a JobLockedException; after
     * voiding, the job is unlocked but still `completed`, so the refusal comes
     * from the terminal-status guard instead. Asserting the specific type is
     * what stops this passing with the lock check deleted — or with the status
     * check deleted.
     */
    public function test_after_voiding_line_items_are_refused_for_status_not_for_the_lock(): void
    {
        $invoice = $this->invoices()->issue($this->job, $this->receptionist);

        $this->invoices()->void($invoice, $this->admin);

        $job = $this->job->fresh();

        $this->assertNull($job->locked_at);
        $this->assertSame(JobStatus::Completed, $job->status);

        try {
            app(JobItemService::class)->addLabor($job, $this->receptionist, [
                'description' => 'Slipped in after the void',
                'quantity' => '1.00',
            ]);
            $this->fail('Expected the line-item write to be refused.');
        } catch (JobLockedException) {
            $this->fail('The job is unlocked — the refusal must come from its status, not its lock.');
        } catch (JobNotAcceptingLineItems) {
            // expected
        }

        $this->assertSame(1, $job->items()->count(), 'No line may have been added.');
    }

    public function test_editing_and_deleting_a_line_are_refused_the_same_way_after_voiding(): void
    {
        $invoice = $this->invoices()->issue($this->job, $this->receptionist);

        $this->invoices()->void($invoice, $this->admin);

        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update'
                    ? app(JobItemService::class)->update($this->line->fresh(), $this->receptionist, [
                        'description' => 'Edited', 'quantity' => '9.00', 'unit_price' => '1.00',
                    ])
                    : app(JobItemService::class)->delete($this->line->fresh(), $this->receptionist);

                $this->fail("Expected {$operation} to be refused on a voided-but-completed job.");
            } catch (JobLockedException) {
                $this->fail("{$operation}: the job is unlocked — the refusal must come from its status.");
            } catch (JobNotAcceptingLineItems) {
                // expected
            }
        }

        $this->assertSame(1, $this->job->items()->count());
    }

    /**
     * Voiding restores the lifecycle controls the lock had withdrawn. Reopening
     * is a front-office act, and it is only reachable because the job unlocked.
     */
    public function test_the_lock_banner_disappears_and_the_controls_return(): void
    {
        $invoice = $this->invoices()->issue($this->job, $this->receptionist);

        $this->actingAs($this->receptionist)
            ->get(route('jobs.show', $this->job))
            ->assertOk()
            ->assertSee(__('jobs.show.lock.title', ['date' => $this->job->fresh()->locked_at->isoFormat('D MMM')]))
            ->assertDontSee('action="'.route('jobs.reopen', $this->job).'"', false);

        $this->invoices()->void($invoice, $this->admin);

        $this->actingAs($this->receptionist)
            ->get(route('jobs.show', $this->job))
            ->assertOk()
            ->assertDontSee(__('jobs.show.lock.body'))
            ->assertSee('action="'.route('jobs.reopen', $this->job).'"', false);
    }

    public function test_a_job_may_hold_several_void_invoices_and_at_most_one_live_one(): void
    {
        foreach (range(1, 3) as $round) {
            $invoice = $this->invoices()->issue($this->job->fresh(), $this->receptionist);

            $this->assertTrue(
                $this->job->fresh()->isLocked(),
                "Round {$round} should have locked the job.",
            );

            $this->invoices()->void($invoice, $this->admin);
        }

        $live = $this->invoices()->issue($this->job->fresh(), $this->receptionist);

        $this->assertSame(3, Invoice::query()->where('status', InvoiceStatus::Void)->count());
        $this->assertSame(1, Invoice::query()->where('status', '!=', InvoiceStatus::Void)->count());
        $this->assertSame($live->getKey(), $this->job->fresh()->activeInvoice()->sole()->getKey());
        $this->assertSame(4, Invoice::query()->count(), 'Nothing is ever deleted.');
    }

    public function test_a_voided_invoice_stays_in_the_list_under_its_own_tab_and_in_all(): void
    {
        $invoice = $this->invoices()->issue($this->job, $this->receptionist);

        $this->invoices()->void($invoice, $this->admin);

        $this->actingAs($this->receptionist)
            ->get(route('invoices.index', ['status' => InvoiceStatus::Void->value]))
            ->assertOk()
            ->assertSee($invoice->number);

        $this->actingAs($this->receptionist)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee($invoice->number);
    }

    public function test_the_job_becomes_invoiceable_again_the_moment_its_only_invoice_is_void(): void
    {
        $invoice = $this->invoices()->issue($this->job, $this->receptionist);

        $this->assertFalse($this->job->fresh()->canBeInvoiced(), 'A locked, invoiced job is not invoiceable.');

        $this->invoices()->void($invoice, $this->admin);

        $this->assertTrue($this->job->fresh()->canBeInvoiced());
    }
}
