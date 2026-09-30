<?php

namespace App\Services;

use Carbon\CarbonInterface;

class EstimateNumberGenerator
{
    public const string PREFIX = 'EST';

    public const int SEQUENCE_DIGITS = ReferenceGenerator::SEQUENCE_DIGITS;

    public function generate(CarbonInterface $on): string
    {
        return ReferenceGenerator::next(self::PREFIX, 'estimates', 'number', $on);
    }
}
