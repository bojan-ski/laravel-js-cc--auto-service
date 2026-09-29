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
}
