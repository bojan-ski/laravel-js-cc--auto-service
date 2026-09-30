<?php

namespace App\Exceptions;

use App\Models\Job;
use RuntimeException;

class CheckInNotOpen extends RuntimeException
{
    public function __construct(
        public readonly Job $job,
    ) {
        parent::__construct(
            "Refused to change the check-in of job {$job->getKey()}: it is no longer open."
        );
    }
}
