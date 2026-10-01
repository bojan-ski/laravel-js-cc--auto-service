<?php

namespace Tests\Feature\Reports;

use App\Enums\PaymentMethod;
use App\Models\Part;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\BuildsReportFixtures;
use Tests\TestCase;

/**
 * The five invariants hold only over invoices issued through
 * InvoiceService::issue(), because only then is each stored subtotal the sum of
 * its locked lines' lineTotal(). The fixture mixes every case the report has to
 * reconcile: discounted lines on both line types, a rate changed between
 * issues, a void and re-issued job, a paid-then-voided invoice, a paid invoice
 * with no method, and a part with no cost price.
 */
class ReportInvariantsTest extends TestCase
{
    use BuildsReportFixtures, LazilyRefreshDatabase;

    private ReportService $reports;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reports = app(ReportService::class);
        $this->from = CarbonImmutable::parse('2026-09-01');
        $this->to = CarbonImmutable::parse('2026-09-30');

        $this->buildFixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00'));
    }

    public function test_the_fixture_exercises_every_case_it_claims(): void
    {
        $revenue = $this->reports->revenue($this->from, $this->to);
        $payments = array_column($this->reports->payments($this->from, $this->to), 'key');
        $parts = $this->reports->partsSold($this->from, $this->to, true);

        $this->assertSame(5, $revenue['invoices']);
        $this->assertSame(2, $revenue['voided']);
        $this->assertTrue($revenue['has_discount']);
        $this->assertCount(2, $this->reports->vatByRate($this->from, $this->to));
        $this->assertSame(['cash', 'card', 'unrecorded', 'unpaid'], $payments);
        $this->assertSame(1, $parts['without_cost']);
    }

    public function test_one_the_revenue_total_is_the_subtotal_plus_vat(): void
    {
        $revenue = $this->reports->revenue($this->from, $this->to);

        $this->assertSame($revenue['total'], bcadd($revenue['subtotal'], $revenue['vat'], 2));
    }

    public function test_two_the_vat_rows_add_up_to_the_subtotal_and_the_vat(): void
    {
        $revenue = $this->reports->revenue($this->from, $this->to);
        $rows = $this->reports->vatByRate($this->from, $this->to);

        $this->assertSame($revenue['subtotal'], $this->sum(array_column($rows, 'base')));
        $this->assertSame($revenue['vat'], $this->sum(array_column($rows, 'vat')));
    }

    public function test_three_the_payment_rows_add_up_to_the_total_and_the_invoice_count(): void
    {
        $revenue = $this->reports->revenue($this->from, $this->to);
        $rows = $this->reports->payments($this->from, $this->to);

        $this->assertSame($revenue['total'], $this->sum(array_column($rows, 'amount')));
        $this->assertSame($revenue['invoices'], array_sum(array_column($rows, 'invoices')));
    }

    public function test_four_labor_and_parts_add_up_to_the_subtotal(): void
    {
        $revenue = $this->reports->revenue($this->from, $this->to);
        $labor = $this->sum(array_column($this->reports->laborByMechanic($this->from, $this->to), 'amount'));
        $parts = $this->reports->partsSold($this->from, $this->to, false)['revenue'];

        $this->assertSame($revenue['subtotal'], bcadd($labor, $parts, 2));
    }

    public function test_five_the_margin_total_is_revenue_less_cost_over_the_parts_with_a_cost(): void
    {
        $parts = $this->reports->partsSold($this->from, $this->to, true);
        $costed = array_filter($parts['rows'], fn (array $row): bool => $row['cost'] !== null);

        $this->assertSame(
            $parts['margin'],
            bcsub($this->sum(array_column($costed, 'revenue')), $this->sum(array_column($costed, 'cost')), 2),
        );
        $this->assertSame($parts['cost'], $this->sum(array_column($costed, 'cost')));
    }

    private function buildFixture(): void
    {
        config(['shop.vat_rate' => '20']);

        $ana = User::factory()->mechanic()->create();
        $boris = User::factory()->mechanic()->create();
        $pads = Part::factory()->create(['cost_price' => '21.40']);
        $oil = Part::factory()->create(['cost_price' => null]);

        $discounted = $this->completedJob();
        $this->laborLine($discounted, $ana, '1.50', '133.33', '7.50');
        $this->partLine($discounted, $pads, 3, '33.33', '5.00');
        $this->payAt($this->issueAt($discounted, '2026-09-02 09:15'), '2026-09-02 10:00', PaymentMethod::Cash);

        $card = $this->completedJob();
        $this->laborLine($card, $boris, '0.75', '99.99', '12.50');
        $this->partLine($card, $oil, 2, '17.45');
        $this->payAt($this->issueAt($card, '2026-09-03 11:00'), '2026-09-03 12:00', PaymentMethod::Card);

        $paidThenVoided = $this->completedJob();
        $this->laborLine($paidThenVoided, $ana, '4.00', '120.00');
        $invoice = $this->issueAt($paidThenVoided, '2026-09-04 08:00');
        $this->payAt($invoice, '2026-09-04 09:00', PaymentMethod::Card);
        $this->voidAt($invoice->fresh(), '2026-09-04 10:00');

        $reissued = $this->completedJob();
        $this->laborLine($reissued, $boris, '2.25', '88.88', '3.00');
        $this->partLine($reissued, $pads, 1, '41.17', '15.00');
        $this->voidAt($this->issueAt($reissued, '2026-09-08 14:00'), '2026-09-08 15:00');

        config(['shop.vat_rate' => '17.5']);

        $this->issueAt($reissued, '2026-09-09 10:00');

        $this->payWithoutMethod($this->invoiceAt('2026-09-10 10:00', '1.25', '76.47', $ana));

        $unpaid = $this->completedJob();
        $this->laborLine($unpaid, $boris, '0.50', '111.11', '33.33');
        $this->partLine($unpaid, $oil, 1, '19.99', '2.50');
        $this->issueAt($unpaid, '2026-09-11 10:00');
    }

    /**
     * @param  list<string>  $amounts
     */
    private function sum(array $amounts): string
    {
        return array_reduce($amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00');
    }
}
