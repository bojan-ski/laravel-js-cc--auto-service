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
}
