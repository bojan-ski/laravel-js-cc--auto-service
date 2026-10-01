<?php

namespace App\Services;

use InvalidArgumentException;

final class Money
{
    public static function round(string $amount): string
    {
        if (bccomp($amount, '0', 20) < 0) {
            throw new InvalidArgumentException("Cannot round a negative amount [{$amount}] half-up.");
        }

        return bcadd($amount, '0.005', 2);
    }

    public static function format(string|int|float $amount): string
    {
        return number_format((float) $amount, 2);
    }

    public static function csv(string $amount): string
    {
        return str_replace('.', ',', $amount);
    }

    public static function trimZeros(string $decimal): string
    {
        if (! str_contains($decimal, '.')) {
            return $decimal;
        }

        $trimmed = rtrim(rtrim($decimal, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * @param  array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}  $totals
     * @return array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    public static function formatTotals(array $totals): array
    {
        foreach (['subtotal', 'tax', 'total', 'discount', 'before_discount'] as $key) {
            $totals[$key] = self::format($totals[$key]);
        }

        return $totals;
    }
}
