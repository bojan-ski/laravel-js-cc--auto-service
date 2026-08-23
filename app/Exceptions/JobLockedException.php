<?php

namespace App\Exceptions;

use App\Models\Job;
use RuntimeException;

class JobLockedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $attemptedChanges
     */
    public function __construct(
        public readonly Job $job,
        public readonly array $attemptedChanges,
    ) {
        $columns = implode(', ', array_keys($attemptedChanges));

        parent::__construct(
            "Job {$job->getKey()} is locked; refused changes to: {$columns}."
        );
    }
}
