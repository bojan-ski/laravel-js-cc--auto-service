<?php

namespace App\Exceptions;

use RuntimeException;

class CheckInSignedException extends RuntimeException
{
    public function __construct(
        public readonly int $jobId,
        public readonly string $record,
    ) {
        parent::__construct(
            "Refused to change {$record} on job {$jobId}: its check-in sheet is signed."
        );
    }
}
