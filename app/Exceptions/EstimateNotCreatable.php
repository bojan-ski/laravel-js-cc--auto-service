<?php

namespace App\Exceptions;

use App\Models\Job;
use RuntimeException;

class EstimateNotCreatable extends RuntimeException
{
    public const string NOT_WORKING = 'not_working';

    public const string LOCKED = 'locked';

    public const string NO_LINE_ITEMS = 'no_line_items';

    public function __construct(
        public readonly Job $job,
        public readonly string $reason,
    ) {
        parent::__construct(
            "Refused to estimate job {$job->getKey()}: {$reason}."
        );
    }
}
