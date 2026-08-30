<?php

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Tax is rounded half-up to two decimals over bcmath strings, which truncate
 * rather than round.
 *
 * These cases deliberately do NOT use the shop's default 20% rate: a subtotal
 * carries two decimals, and two decimals times 0.2 always leaves an even digit
 * in the third place, so a half-cent is unreachable at 20%. Only a rate with
 * its own decimals can produce one.
 */
class InvoiceTaxRoundingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function issueWith(string $rate, string $subtotal): Invoice
    {
        config(['shop.vat_rate' => $rate]);

        $job = Job::factory()->completed()->create();
        JobItem::factory()->forJob($job)->create([
            'quantity' => 1,
            'unit_price' => $subtotal,
        ]);

        return app(InvoiceService::class)->issue($job, User::factory()->receptionist()->create());
    }

    public function test_a_half_cent_rounds_up(): void
    {
        $invoice = $this->issueWith('17.5', '3.00');

        $this->assertSame('3.00', $invoice->subtotal);
        $this->assertSame('0.53', $invoice->tax_amount, '3.00 at 17.5% is exactly 0.525.');
        $this->assertSame('3.53', $invoice->total);
    }

    public function test_below_a_half_cent_rounds_down(): void
    {
        $invoice = $this->issueWith('12.34', '1.00');

        $this->assertSame('0.12', $invoice->tax_amount, '1.00 at 12.34% is 0.1234.');
        $this->assertSame('1.12', $invoice->total);
    }

    public function test_above_a_half_cent_rounds_up(): void
    {
        $invoice = $this->issueWith('12.6', '1.00');

        $this->assertSame('0.13', $invoice->tax_amount, '1.00 at 12.6% is 0.126.');
        $this->assertSame('1.13', $invoice->total);
    }

    public function test_an_exact_two_decimal_tax_is_left_alone(): void
    {
        $invoice = $this->issueWith('17.5', '2.00');

        $this->assertSame('0.35', $invoice->tax_amount);
        $this->assertSame('2.35', $invoice->total);
    }

    public function test_the_total_is_the_subtotal_plus_the_rounded_tax(): void
    {
        $invoice = $this->issueWith('17.5', '3.00');

        $this->assertSame(
            bcadd($invoice->subtotal, $invoice->tax_amount, 2),
            $invoice->total,
            'The total must be built from the rounded tax, not recomputed from the raw fraction.',
        );
    }
}
