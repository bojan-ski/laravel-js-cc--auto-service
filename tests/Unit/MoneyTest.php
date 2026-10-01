<?php

namespace Tests\Unit;

use App\Services\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function vectors(): array
    {
        return [
            'a third decimal of 6 rounds up' => ['990.0165', '990.02'],
            'exactly half a cent rounds up' => ['0.005', '0.01'],
            'a binary-float trap rounds up' => ['2.675', '2.68'],
            'an integer gains two decimals' => ['5', '5.00'],
            'just under half a cent rounds down' => ['0.004999', '0.00'],
            'zero stays zero' => ['0', '0.00'],
            'two decimals pass through' => ['12.34', '12.34'],
        ];
    }

    #[DataProvider('vectors')]
    public function test_it_rounds_half_up_to_two_decimals(string $amount, string $expected): void
    {
        $this->assertSame($expected, Money::round($amount));
    }

    public function test_a_negative_amount_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::round('-0.01');
    }

    public function test_a_negative_amount_below_a_hundredth_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::round('-0.0001');
    }

    /**
     * @return array<string, array{string|int|float, string}>
     */
    public static function formatVectors(): array
    {
        return [
            'zero' => ['0.00', '0.00'],
            'an integer zero' => [0, '0.00'],
            'a sub-unit string' => ['0.5', '0.50'],
            'a thousands integer' => [3000, '3,000.00'],
            'a thousands float' => [1234.5, '1,234.50'],
            'a millions string' => ['1234567.89', '1,234,567.89'],
            'a stored two-decimal string' => ['10312.50', '10,312.50'],
        ];
    }

    #[DataProvider('formatVectors')]
    public function test_it_formats_an_amount_with_two_decimals_and_thousands_separators(string|int|float $amount, string $expected): void
    {
        $this->assertSame($expected, Money::format($amount));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function trimVectors(): array
    {
        return [
            'a whole rate' => ['20.00', '20'],
            'one significant decimal' => ['17.50', '17.5'],
            'another significant decimal' => ['12.60', '12.6'],
            'a sub-unit value' => ['0.50', '0.5'],
            'a hundred' => ['100.00', '100'],
            'zero with decimals' => ['0.00', '0'],
            'already trimmed' => ['1.5', '1.5'],
            'no decimal point keeps its zeros' => ['100', '100'],
            'a bare zero' => ['0', '0'],
        ];
    }

    #[DataProvider('trimVectors')]
    public function test_it_trims_trailing_zeros_from_a_stored_decimal(string $decimal, string $expected): void
    {
        $this->assertSame($expected, Money::trimZeros($decimal));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function csvVectors(): array
    {
        return [
            'a stored amount' => ['1234.50', '1234,50'],
            'thousands are never grouped' => ['1234567.89', '1234567,89'],
            'a rate' => ['20.00', '20,00'],
            'zero' => ['0.00', '0,00'],
            'no fractional part' => ['1234', '1234'],
        ];
    }

    #[DataProvider('csvVectors')]
    public function test_it_writes_a_stored_decimal_with_a_decimal_comma_for_the_csv(string $amount, string $expected): void
    {
        $this->assertSame($expected, Money::csv($amount));
    }

    public function test_it_formats_exactly_the_five_amounts_of_a_totals_array(): void
    {
        $formatted = Money::formatTotals([
            'subtotal' => '7800.00',
            'rate' => '17.5',
            'tax' => '1365.00',
            'total' => '9165.00',
            'discount' => '1200.00',
            'before_discount' => '9000.00',
            'has_discount' => true,
        ]);

        $this->assertSame([
            'subtotal' => '7,800.00',
            'rate' => '17.5',
            'tax' => '1,365.00',
            'total' => '9,165.00',
            'discount' => '1,200.00',
            'before_discount' => '9,000.00',
            'has_discount' => true,
        ], $formatted);
    }

    public function test_formatting_totals_passes_the_rate_and_the_flag_through_untouched(): void
    {
        $formatted = Money::formatTotals([
            'subtotal' => '1000.00',
            'rate' => '1000',
            'tax' => '0.00',
            'total' => '1000.00',
            'discount' => '0.00',
            'before_discount' => '1000.00',
            'has_discount' => false,
        ]);

        $this->assertSame('1000', $formatted['rate']);
        $this->assertFalse($formatted['has_discount']);
    }
}
