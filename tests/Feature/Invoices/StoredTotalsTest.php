<?php

namespace Tests\Feature\Invoices;

use App\Models\Estimate;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoredTotalsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return [
            'invoice' => [Invoice::class],
            'estimate' => [Estimate::class],
        ];
    }

    /**
     * @param  class-string<Invoice|Estimate>  $model
     * @param  array<string, string>  $columns
     */
    private function record(string $model, array $columns): Invoice|Estimate
    {
        return $model::factory()->create($columns)->fresh();
    }

    /**
     * @param  class-string<Invoice|Estimate>  $model
     */
    #[DataProvider('models')]
    public function test_a_discounted_record_reports_its_stored_columns_and_the_amount_before_discount(string $model): void
    {
        $totals = $this->record($model, [
            'subtotal' => '7800.00',
            'discount_total' => '200.00',
            'tax_rate' => '20.00',
            'tax_amount' => '1560.00',
            'total' => '9360.00',
        ])->storedTotals();

        $this->assertSame([
            'subtotal' => '7800.00',
            'rate' => '20',
            'tax' => '1560.00',
            'total' => '9360.00',
            'discount' => '200.00',
            'before_discount' => '8000.00',
            'has_discount' => true,
        ], $totals);
    }

    /**
     * @param  class-string<Invoice|Estimate>  $model
     */
    #[DataProvider('models')]
    public function test_an_undiscounted_record_has_no_discount_and_an_unchanged_amount_before_discount(string $model): void
    {
        $record = $this->record($model, [
            'subtotal' => '2500.50',
            'discount_total' => '0.00',
            'tax_rate' => '20.00',
            'tax_amount' => '500.10',
            'total' => '3000.60',
        ]);

        $totals = $record->storedTotals();

        $this->assertFalse($record->hasDiscount());
        $this->assertFalse($totals['has_discount']);
        $this->assertSame('0.00', $totals['discount']);
        $this->assertSame('2500.50', $totals['before_discount']);
    }

    /**
     * @param  class-string<Invoice|Estimate>  $model
     */
    #[DataProvider('models')]
    public function test_the_tax_rate_label_drops_only_insignificant_zeros(string $model): void
    {
        $record = $this->record($model, ['tax_rate' => '17.50']);

        $this->assertSame('17.5', $record->taxRateLabel());
        $this->assertSame('17.5', $record->storedTotals()['rate']);
    }

    public function test_an_invoice_and_an_estimate_with_the_same_columns_report_the_same_totals(): void
    {
        $columns = [
            'subtotal' => '10312.50',
            'discount_total' => '187.50',
            'tax_rate' => '12.60',
            'tax_amount' => '1299.38',
            'total' => '11611.88',
        ];

        $this->assertSame(
            $this->record(Invoice::class, $columns)->storedTotals(),
            $this->record(Estimate::class, $columns)->storedTotals(),
        );
    }
}
