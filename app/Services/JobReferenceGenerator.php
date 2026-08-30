<?php

namespace App\Services;

use Carbon\CarbonInterface;

class JobReferenceGenerator
{
    public const string PREFIX = 'JOB';

    public const int SEQUENCE_DIGITS = ReferenceGenerator::SEQUENCE_DIGITS;

    public function generate(CarbonInterface $on): string
    {
        return ReferenceGenerator::next(self::PREFIX, 'jobs', 'reference', $on);
    }
}
