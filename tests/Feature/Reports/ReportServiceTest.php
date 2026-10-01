<?php

namespace Tests\Feature\Reports;

use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\BuildsReportFixtures;
use Tests\TestCase;

/**
 * A SUM over no rows is NULL, so every figure must fall back to '0.00' and
 * every count to 0. A period that holds invoices only on its far side proves
 * the fallback rather than an empty table.
 */
class ReportServiceTest extends TestCase
{
    use BuildsReportFixtures, LazilyRefreshDatabase;

    public function test_every_method_returns_zeros_and_empty_lists_for_an_empty_period(): void
    {
        $this->invoiceAt('2026-08-31 23:59:59');
        $this->invoiceAt('2026-10-01 00:00:00');

        $reports = app(ReportService::class);
        $from = CarbonImmutable::parse('2026-09-01');
        $to = CarbonImmutable::parse('2026-09-30');

        $this->assertSame([
            'invoices' => 0,
            'before_discount' => '0.00',
            'discount' => '0.00',
            'subtotal' => '0.00',
            'vat' => '0.00',
            'total' => '0.00',
            'has_discount' => false,
            'voided' => 0,
        ], $reports->revenue($from, $to));

        $this->assertSame([], $reports->vatByRate($from, $to));
        $this->assertSame([], $reports->payments($from, $to));
        $this->assertSame([], $reports->laborByMechanic($from, $to));

        foreach ([true, false] as $withMargin) {
            $this->assertSame([
                'rows' => [],
                'quantity' => 0,
                'revenue' => '0.00',
                'cost' => $withMargin ? '0.00' : null,
                'margin' => $withMargin ? '0.00' : null,
                'without_cost' => 0,
            ], $reports->partsSold($from, $to, $withMargin));
        }
    }

    public function test_figures_come_back_as_exact_decimal_strings(): void
    {
        $this->invoiceAt('2026-09-05 10:00', '1.00', '0.10');
        $this->invoiceAt('2026-09-06 10:00', '1.00', '0.20');

        $revenue = app(ReportService::class)->revenue(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'));

        $this->assertSame('0.30', $revenue['subtotal']);
        $this->assertSame('0.36', $revenue['total']);
        $this->assertSame(2, $revenue['invoices']);
    }

    public function test_the_export_of_an_empty_period_is_the_bom_and_the_header_line(): void
    {
        $lines = iterator_to_array(app(ReportService::class)->exportLines(
            CarbonImmutable::parse('2026-09-01'),
            CarbonImmutable::parse('2026-09-30'),
        ), false);

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $lines[0]);
    }
}
