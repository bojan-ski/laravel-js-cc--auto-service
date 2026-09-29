<?php

namespace Tests\Unit;

use App\Services\TaxIdChecksum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxIdChecksumTest extends TestCase
{
    /**
     * @return array<string, array{string, bool, int}>
     */
    public static function vectors(): array
    {
        return [
            '100002887 valid' => ['100002887', true, 7],
            '104052135 valid' => ['104052135', true, 5],
            '101234569 valid' => ['101234569', true, 9],
            '101234568 expects 9' => ['101234568', false, 9],
            '100000009 expects 8' => ['100000009', false, 8],
            '000000000 expects 3' => ['000000000', false, 3],
        ];
    }

    #[DataProvider('vectors')]
    public function test_the_verified_vectors(string $taxId, bool $valid, int $checkDigit): void
    {
        $this->assertSame($valid, TaxIdChecksum::isValid($taxId));
        $this->assertSame($checkDigit, TaxIdChecksum::checkDigit(substr($taxId, 0, 8)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformed(): array
    {
        return [
            'eight digits' => ['10123456'],
            'ten digits' => ['1012345690'],
            'a letter in place of a digit' => ['10123456A'],
            'a space inside' => ['101 234 569'],
            'empty' => [''],
            'a trailing newline' => ["101234569\n"],
        ];
    }

    #[DataProvider('malformed')]
    public function test_anything_but_nine_digits_is_invalid(string $taxId): void
    {
        $this->assertFalse(TaxIdChecksum::isValid($taxId));
    }

    public function test_exactly_one_ninth_digit_is_accepted_for_any_prefix(): void
    {
        foreach (['10123456', '00000000', '99999999', '12345678'] as $prefix) {
            $accepted = array_filter(range(0, 9), fn (int $digit): bool => TaxIdChecksum::isValid($prefix.$digit));

            $this->assertSame([TaxIdChecksum::checkDigit($prefix)], array_values($accepted), "Prefix {$prefix}");
        }
    }
}
