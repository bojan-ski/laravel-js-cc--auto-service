<?php

namespace App\Services;

use App\Models\Job;
use Carbon\CarbonInterface;

class JobReferenceGenerator
{
    public const string PREFIX = 'JOB';

    public const int SEQUENCE_DIGITS = 4;

    public function generate(CarbonInterface $on): string
    {
        $prefix = sprintf('%s-%s-', self::PREFIX, $on->format('Y'));

        $last = Job::query()
            ->where('reference', 'like', $prefix.'%')
            ->max('reference');

        $next = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $next, self::SEQUENCE_DIGITS, '0', STR_PAD_LEFT);
    }
}
