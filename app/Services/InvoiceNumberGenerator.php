<?php

namespace App\Services;

use Carbon\CarbonInterface;

class InvoiceNumberGenerator
{
    public const string PREFIX = 'INV';

    public const int SEQUENCE_DIGITS = ReferenceGenerator::SEQUENCE_DIGITS;

    public function generate(CarbonInterface $on): string
    {
        return ReferenceGenerator::next(self::PREFIX, 'invoices', 'number', $on);
    }
}
