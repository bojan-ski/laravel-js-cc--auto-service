<?php

namespace Tests\Feature\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceShowViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function jobFor(string $customerName): Job
    {
        $customer = Customer::factory()->create(['name' => $customerName]);
        $vehicle = Vehicle::factory()->for($customer)->create([
            'registration_plate' => 'BG123-ZZ',
            'make' => 'Volkswagen',
            'model' => 'Golf IV',
        ]);

        return Job::factory()->for($customer)->for($vehicle)->completed()->create();
    }

    private function issued(string $customerName = 'Milica Petrović'): Invoice
    {
        $job = $this->jobFor($customerName);
        JobItem::factory()->forJob($job)->create([
            'description' => 'Brake service',
            'quantity' => 1.5,
            'unit_price' => 2000,
        ]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    private function page(Invoice $invoice): string
    {
        return $this->actingAs(User::factory()->receptionist()->create())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->getContent();
    }

    private function totalsBlock(string $html): string
    {
        preg_match('/<dl class="border border-border print:break-inside-avoid".*?<\/dl>/s', $html, $block);

        $this->assertNotEmpty($block, 'The totals block did not render.');

        return $block[0];
    }

    public function test_the_page_shows_every_documented_fact(): void
    {
        $invoice = $this->issued();
        $invoice->due_at = now()->addDays(14);
        $invoice->save();

        $html = $this->page($invoice);

        foreach ([
            $invoice->number,
            $invoice->job->reference,
            'Milica Petrović',
            'BG123-ZZ',
            'Golf IV',
            $invoice->issuedBy->name,
            $invoice->issued_at->isoFormat('D MMM YYYY'),
            $invoice->due_at->isoFormat('D MMM YYYY'),
            __('invoices.statuses.unpaid'),
        ] as $fact) {
            $this->assertStringContainsString($fact, $html, "The page must show {$fact}.");
        }
    }

    public function test_a_due_date_that_was_never_set_renders_as_no_value(): void
    {
        $invoice = $this->issued();

        $this->assertNull($invoice->due_at);
        $this->assertStringContainsString(__('app.value_none_spoken'), $this->page($invoice));
    }

    /**
     * The money on this page is what was invoiced, not what the job's lines add
     * up to today. Storing 8,888.00 against lines worth 3,333.00 is impossible
     * in production, and is exactly what proves the page reads the columns.
     */
    public function test_the_totals_are_the_stored_figures_not_a_recomputation(): void
    {
        $job = $this->jobFor('A. Nikolić');
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 3333]);

        $invoice = Invoice::factory()->forJob($job)->create([
            'subtotal' => '8888.00',
            'tax_rate' => '20.00',
            'tax_amount' => '1777.60',
            'total' => '10665.60',
        ]);

        $totals = $this->totalsBlock($this->page($invoice));

        $this->assertStringContainsString('8,888.00', $totals);
        $this->assertStringContainsString('1,777.60', $totals);
        $this->assertStringContainsString('10,665.60', $totals);
        $this->assertStringNotContainsString('3,333.00', $totals, 'The totals must not be recomputed from the lines.');
    }

    /**
     * `tax_rate` is cast decimal:2, so the raw column reads "20.00". The issue
     * page prints "VAT 20%" from config, and one feature must not print the same
     * figure two ways.
     */
    public function test_the_vat_label_prints_a_trimmed_rate(): void
    {
        $invoice = $this->issued();

        $this->assertSame('20.00', $invoice->tax_rate);
        $this->assertStringContainsString(
            __('invoices.totals.vat', ['rate' => '20']),
            $this->totalsBlock($this->page($invoice)),
        );
    }

    /**
     * A rate with a real fraction must keep it — trimming is about dropping the
     * cast's padding, not about rounding to whole percents.
     */
    public function test_a_fractional_vat_rate_keeps_its_fraction(): void
    {
        $job = $this->jobFor('B. Marković');
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 1000]);

        $invoice = Invoice::factory()->forJob($job)->create(['tax_rate' => '17.50']);

        $this->assertStringContainsString(
            __('invoices.totals.vat', ['rate' => '17.5']),
            $this->totalsBlock($this->page($invoice)),
        );
    }

    public function test_each_line_shows_its_quantity_unit_price_and_line_total(): void
    {
        $job = $this->jobFor('D. Jovanović');
        JobItem::factory()->forJob($job)->create([
            'description' => 'Brake service',
            'quantity' => 1.5,
            'unit_price' => 2000,
        ]);

        $invoice = Invoice::factory()->forJob($job)->create();

        preg_match('/<section class="mt-4">.*?<\/section>/s', $this->page($invoice), $section);

        $this->assertNotEmpty($section, 'The line items section did not render.');
        $this->assertStringContainsString('Brake service', $section[0]);
        $this->assertStringContainsString('>1.5<', $section[0], 'The quantity renders trimmed, not as 1.50.');
        $this->assertStringContainsString('2,000.00', $section[0]);
        $this->assertStringContainsString('3,000.00', $section[0], 'The line total is quantity times unit price.');
    }

    public function test_a_non_void_invoice_lists_lines_that_sum_to_the_stored_subtotal(): void
    {
        $job = $this->jobFor('J. Ilić');
        JobItem::factory()->forJob($job)->create(['description' => 'Brake service', 'quantity' => 2, 'unit_price' => 1500]);
        JobItem::factory()->forJob($job)->create(['description' => 'Pad set front', 'quantity' => 1, 'unit_price' => 4200]);

        $invoice = app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());

        $html = $this->page($invoice);

        $this->assertStringContainsString(__('invoices.show.line_items'), $html);
        $this->assertStringContainsString('Brake service', $html);
        $this->assertStringContainsString('Pad set front', $html);
        $this->assertSame('7200.00', $invoice->subtotal);
        $this->assertStringContainsString('7,200.00', $this->totalsBlock($html));
    }

    /**
     * Voiding reopens the job, so its lines may no longer reconcile with the
     * frozen totals. Showing both would put two disagreeing sets of figures on
     * one page and invite trusting the wrong one.
     */
    public function test_a_void_invoice_shows_no_lines_but_keeps_its_totals(): void
    {
        $job = $this->jobFor('M. Petrović');
        JobItem::factory()->forJob($job)->create(['description' => 'Brake service', 'quantity' => 1, 'unit_price' => 2000]);

        $invoice = Invoice::factory()->forJob($job)->void()->create(['subtotal' => '2000.00', 'total' => '2400.00']);

        $html = $this->page($invoice);

        $this->assertStringNotContainsString(__('invoices.show.line_items'), $html);
        $this->assertStringNotContainsString('Brake service', $html);

        $this->assertStringContainsString(__('invoices.void.title'), $html);
        $this->assertStringContainsString(__('invoices.void.body'), $html);
        $this->assertStringContainsString('href="'.route('jobs.show', $job).'"', $html);

        $this->assertStringContainsString('2,400.00', $this->totalsBlock($html));
    }

    /**
     * @return array<int, string>
     */
    private function headingOutline(string $html): array
    {
        preg_match_all('/<(h[1-6])\b[^>]*>(.*?)<\/\1>/s', $html, $headings, PREG_SET_ORDER);

        return array_map(
            fn (array $match): string => $match[1].':'.trim(strip_tags($match[2])),
            $headings,
        );
    }

    /**
     * The void notice replaces the line-items section, so if one carries a
     * heading and the other does not, a voided invoice loses a level of the
     * outline that every other invoice has (WCAG 1.3.1).
     */
    public function test_both_invoice_states_expose_the_same_heading_outline(): void
    {
        $job = $this->jobFor('M. Petrović');
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $nonVoid = $this->headingOutline($this->page(Invoice::factory()->forJob($job)->create()));
        $void = $this->headingOutline($this->page(Invoice::factory()->forJob($job)->void()->create()));

        $this->assertSame(
            array_map(fn (string $heading): string => explode(':', $heading)[0], $nonVoid),
            array_map(fn (string $heading): string => explode(':', $heading)[0], $void),
            'A void invoice must not lose a heading its non-void sibling has.',
        );

        $this->assertContains('h2:'.__('invoices.void.title'), $void);
        $this->assertContains('h2:'.__('invoices.show.line_items'), $nonVoid);
    }

    public function test_it_renders_with_customer_vehicle_and_issuer_all_soft_deleted(): void
    {
        $invoice = $this->issued();

        $invoice->job->customer->delete();
        $invoice->job->vehicle->delete();
        $invoice->issuedBy->delete();

        $html = $this->page($invoice->fresh());

        $this->assertStringContainsString('Milica Petrović', $html);
        $this->assertStringContainsString('BG123-ZZ', $html);
        $this->assertStringContainsString(__('app.deleted'), $html);
    }

    public function test_the_detail_page_issues_a_pinned_number_of_queries(): void
    {
        $invoice = $this->issued();
        $user = User::factory()->receptionist()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(6, $queries, "Expected 6 queries:\n".implode("\n", array_column($queries, 'query')));
    }

    /**
     * voidedBy is eager-loaded unconditionally. On a live invoice that costs
     * nothing — Laravel skips a belongs-to eager query when every foreign key is
     * null — so only a void invoice pays for the relation, and it trades that
     * one query for the line-items query it no longer issues.
     */
    public function test_a_void_invoice_detail_page_issues_a_pinned_number_of_queries(): void
    {
        $job = $this->jobFor('M. Petrović');
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        $invoice = Invoice::factory()->forJob($job)->void()->create();
        $user = User::factory()->receptionist()->create();

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('invoices.show', $invoice))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(6, $queries, "Expected 6 queries:\n".implode("\n", array_column($queries, 'query')));
    }
}
