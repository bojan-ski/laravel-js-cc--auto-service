<?php

namespace App\Exceptions;

use App\Enums\JobStatus;
use App\Models\Job;
use RuntimeException;

class InvalidJobTransition extends RuntimeException
{
    public readonly JobStatus $status;

    public function __construct(
        public readonly Job $job,
        public readonly string $transition,
    ) {
        $this->status = $job->status;

        parent::__construct(
            "Cannot {$transition} job {$job->getKey()} from status {$this->status->value}."
        );
    }
}
