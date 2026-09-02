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
use Tests\TestCase;

/**
 * PHPUnit evaluates no CSS and no media queries, so "hidden when printed"
 * cannot pass or fail honestly here. These assertions read class strings only.
 * The printed output itself — what a sheet of paper actually shows — is NOT
 * covered by this suite.
 */
class InvoicePrintMarkupTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function issued(?string $address = null): Invoice
    {
        $customer = Customer::factory()->create(['name' => 'Milica Petrović', 'address' => $address]);
        $vehicle = Vehicle::factory()->for($customer)->create();
        $job = Job::factory()->for($customer)->for($vehicle)->completed()->create();
        JobItem::factory()->forJob($job)->create(['quantity' => 1, 'unit_price' => 2000]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    private function page(Invoice $invoice, bool $withFlash = false): string
    {
        $request = $this->actingAs(User::factory()->receptionist()->create());

        if ($withFlash) {
            $request = $request->withSession(['success' => 'Something happened.']);
        }

        return $request->get(route('invoices.show', $invoice))->assertOk()->getContent();
    }

    public function test_the_navigation_wrapper_is_hidden_in_print(): void
    {
        $html = $this->page($this->issued());

        $this->assertMatchesRegularExpression(
            '/<header[^>]*\bclass="[^"]*\bprint:hidden\b[^"]*"/',
            $html,
            'The nav header must carry print:hidden.',
        );
    }

    public function test_the_flash_region_is_hidden_in_print(): void
    {
        $html = $this->page($this->issued(), withFlash: true);

        $this->assertStringContainsString('data-flash="success"', $html, 'The flash region must render for this to mean anything.');
        $this->assertMatchesRegularExpression(
            '/<div\s+class="[^"]*\bprint:hidden\b[^"]*"\s+data-flash="success"/',
            $html,
            'The flash region must carry print:hidden.',
        );
    }

    public function test_the_action_row_is_hidden_in_print(): void
    {
        $invoice = $this->issued();
        $html = $this->page($invoice);

        preg_match('/<div class="[^"]*print:hidden[^"]*">\s*<h2 class="sr-only">'.preg_quote(__('invoices.show.actions'), '/').'/', $html, $row);

        $this->assertNotEmpty($row, 'The invoice action row must carry print:hidden.');
        $this->assertStringContainsString(route('invoices.paid', $invoice), $html, 'The row must actually hold a control.');
    }

    /**
     * The print letterhead already names the invoice, so printing the screen's
     * <h1> too would put the number on the page twice. The status badge beside
     * it stays — whether a printed invoice is paid is worth reading on paper.
     */
    public function test_the_screen_heading_is_not_printed_because_the_letterhead_names_it(): void
    {
        $invoice = $this->issued();
        $html = $this->page($invoice);

        $this->assertMatchesRegularExpression(
            '/<h1 class="[^"]*\bprint:hidden\b[^"]*">\s*'.preg_quote($invoice->number, '/').'\s*<\/h1>/',
            $html,
        );

        $this->assertStringContainsString(
            __('invoices.identity.heading', ['number' => $invoice->number]),
            $html,
            'The letterhead must carry the number the heading gives up.',
        );

        $this->assertStringContainsString(__('invoices.statuses.unpaid'), $html, 'The status still prints.');
    }

    public function test_the_identity_block_is_print_only(): void
    {
        $html = $this->page($this->issued());

        $this->assertMatchesRegularExpression(
            '/<div class="hidden print:block">/',
            $html,
            'The shop identity block must be hidden on screen and shown in print.',
        );

        $this->assertStringContainsString(config('shop.identity.name'), $html);
        $this->assertStringContainsString(config('shop.identity.address'), $html);
        $this->assertStringContainsString(config('shop.identity.tax_number'), $html);
    }

    /**
     * The Bill-to block only — the shop's own address lives higher up in the
     * identity block and would satisfy a whole-page assertion by itself.
     */
    private function billToBlock(string $html): string
    {
        preg_match(
            '/<p class="text-xs font-medium uppercase tracking-wider">\s*'
                .preg_quote(__('invoices.identity.bill_to'), '/').'\s*<\/p>.*?<\/div>/s',
            $html,
            $block,
        );

        $this->assertNotEmpty($block, 'The Bill-to block did not render.');

        return $block[0];
    }

    public function test_a_customer_address_is_printed_when_one_is_on_file(): void
    {
        $billTo = $this->billToBlock($this->page($this->issued('Kneza Miloša 40, Beograd')));

        $this->assertStringContainsString('Milica Petrović', $billTo);
        $this->assertStringContainsString('Kneza Miloša 40, Beograd', $billTo);
        $this->assertSame(3, preg_match_all('/<p\b/', $billTo), 'The Bill-to label, the name and the address each render.');
    }

    /**
     * A blank line on a printed document reads as a defect, so a customer with
     * no address must emit no element at all rather than an empty one.
     */
    public function test_a_customer_without_an_address_emits_no_empty_line(): void
    {
        $invoice = $this->issued();

        $this->assertNull($invoice->job->customer->address);

        $billTo = $this->billToBlock($this->page($invoice));

        $this->assertStringContainsString('Milica Petrović', $billTo);
        $this->assertDoesNotMatchRegularExpression('/<p>\s*<\/p>/', $billTo, 'An absent address must emit no element.');
        $this->assertSame(2, preg_match_all('/<p\b/', $billTo), 'Only the Bill-to label and the name may render — no empty address line.');
    }

    /**
     * One template serves screen and print, so the printed figures are the
     * screen figures by construction rather than by discipline.
     */
    public function test_the_totals_block_appears_exactly_once(): void
    {
        $html = $this->page($this->issued());

        $this->assertSame(
            1,
            preg_match_all('/<dl class="border border-border print:break-inside-avoid"/', $html),
            'A second totals block would be a second set of figures to keep in step.',
        );
    }

    /**
     * A printed void invoice must not be mistakable for a live one. Whether the
     * marker is visually prominent on paper is outside what this suite can see —
     * only its presence in the print-only letterhead is asserted.
     */
    public function test_a_void_invoice_carries_a_print_marker_and_a_live_one_does_not(): void
    {
        $live = $this->issued();

        $this->assertStringNotContainsString(
            __('invoices.void.print_marker'),
            $this->page($live),
            'A live invoice must never print a VOID marker.',
        );

        $voided = $this->issued();
        app(InvoiceService::class)->void($voided, User::factory()->admin()->create());

        $html = $this->page($voided->fresh());

        $this->assertStringContainsString(__('invoices.void.print_marker'), $html);

        preg_match('/<div class="hidden print:block">.*?<\/div>\s*<div class="mt-6">/s', $html, $letterhead);

        $this->assertNotEmpty($letterhead, 'The print-only letterhead did not render.');
        $this->assertStringContainsString(
            __('invoices.void.print_marker'),
            $letterhead[0],
            'The marker must live inside the print-only letterhead, not on the screen page.',
        );
    }

    public function test_the_print_control_is_a_slot_for_javascript_to_fill(): void
    {
        $html = $this->page($this->issued());

        $this->assertStringContainsString(
            '<span data-print-slot data-print-label="'.e(__('invoices.show.print')).'"></span>',
            $html,
            'With JS off the slot must stay empty rather than render a dead button.',
        );
    }
}
