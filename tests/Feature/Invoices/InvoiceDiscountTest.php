<?php

namespace Tests\Feature\Invoices;

use App\Exceptions\JobLockedException;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\JobItemService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InvoiceDiscountTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function completedJobWithLines(): Job
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->discounted('10.00')->create(['quantity' => '1.50', 'unit_price' => '3000.00']);
        JobItem::factory()->forJob($job)->part()->create(['quantity' => 3, 'unit_price' => '19.99']);

        return $job;
    }

    private function issue(Job $job): Invoice
    {
        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    public function test_issuing_stores_the_discounted_subtotal_and_the_discount_total(): void
    {
        $invoice = $this->issue($this->completedJobWithLines())->fresh();

        $this->assertSame('4109.97', $invoice->subtotal);
        $this->assertSame('450.00', $invoice->discount_total);
        $this->assertSame('821.99', $invoice->tax_amount);
        $this->assertSame('4931.96', $invoice->total);
        $this->assertTrue($invoice->hasDiscount());
    }

    public function test_an_undiscounted_job_issues_a_zero_discount_total(): void
    {
        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => '1.00', 'unit_price' => '1000.00']);

        $invoice = $this->issue($job)->fresh();

        $this->assertSame('0.00', $invoice->discount_total);
        $this->assertFalse($invoice->hasDiscount());
    }

    public function test_a_discount_set_on_the_completed_job_before_issue_is_on_the_invoice(): void
    {
        $job = Job::factory()->completed()->create();
        $item = JobItem::factory()->forJob($job)->create(['quantity' => '1.00', 'unit_price' => '1000.00']);

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '25'])
            ->assertRedirect(route('jobs.show', $job));

        $invoice = $this->issue($job)->fresh();

        $this->assertSame('750.00', $invoice->subtotal);
        $this->assertSame('250.00', $invoice->discount_total);
        $this->assertSame('150.00', $invoice->tax_amount);
    }

    public function test_a_discount_attempted_after_issue_is_refused_by_the_route_and_by_the_service(): void
    {
        $job = $this->completedJobWithLines();
        $item = $job->items()->firstOrFail();
        $invoice = $this->issue($job);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '50'])
            ->assertForbidden();

        try {
            app(JobItemService::class)->setDiscount($item, '50.00');
            $this->fail('The service accepted a discount on an invoiced job.');
        } catch (JobLockedException) {
            $this->assertSame('10.00', $item->fresh()->discount_percent);
            $this->assertSame('450.00', $invoice->fresh()->discount_total);
        }
    }

    public function test_a_void_invoice_keeps_its_discount_and_the_reissue_picks_up_the_change(): void
    {
        $job = $this->completedJobWithLines();
        $item = $job->items()->where('type', 'labor')->firstOrFail();
        $first = $this->issue($job);

        app(InvoiceService::class)->void($first, User::factory()->admin()->create());

        $this->actingAs(User::factory()->receptionist()->create())
            ->patch(route('job-items.discount.update', $item), ['discount_percent' => '20'])
            ->assertRedirect(route('jobs.show', $job));

        $second = $this->issue($job->fresh())->fresh();
        $first = $first->fresh();

        $this->assertTrue($first->isVoid());
        $this->assertSame('450.00', $first->discount_total);
        $this->assertSame('4109.97', $first->subtotal);

        $this->assertSame('900.00', $second->discount_total);
        $this->assertSame('3659.97', $second->subtotal);
        $this->assertNotSame($first->number, $second->number);
    }

    public function test_the_live_totals_carry_the_discount_keys(): void
    {
        $job = $this->completedJobWithLines();
        $job->load('items');

        $totals = app(InvoiceService::class)->totalsFor($job);

        $this->assertSame('4109.97', $totals['subtotal']);
        $this->assertSame('450.00', $totals['discount']);
        $this->assertSame('4559.97', $totals['before_discount']);
        $this->assertTrue($totals['has_discount']);
    }

    public function test_the_stored_totals_carry_the_discount_keys_from_the_columns(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => '900.00',
            'discount_total' => '100.00',
            'tax_amount' => '180.00',
            'total' => '1080.00',
        ]);

        $totals = $invoice->storedTotals();

        $this->assertSame('100.00', $totals['discount']);
        $this->assertSame('1000.00', $totals['before_discount']);
        $this->assertTrue($totals['has_discount']);

        $plain = Invoice::factory()->create()->storedTotals();

        $this->assertSame('0.00', $plain['discount']);
        $this->assertSame($plain['subtotal'], $plain['before_discount']);
        $this->assertFalse($plain['has_discount']);
    }
}
