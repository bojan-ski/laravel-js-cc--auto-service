<?php

namespace App\Services;

final class TaxIdChecksum
{
    public static function isValid(string $taxId): bool
    {
        if (preg_match('/^\d{9}\z/', $taxId) !== 1) {
            return false;
        }

        return self::checkDigit(substr($taxId, 0, 8)) === (int) $taxId[8];
    }

    public static function checkDigit(string $firstEightDigits): int
    {
        $product = 10;

        foreach (str_split($firstEightDigits) as $digit) {
            $sum = ((int) $digit + $product) % 10;
            $product = (2 * ($sum === 0 ? 10 : $sum)) % 11;
        }

        return (11 - $product) % 10;
    }
}
