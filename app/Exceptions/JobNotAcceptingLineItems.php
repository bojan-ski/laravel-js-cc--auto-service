<?php

namespace App\Exceptions;

use App\Enums\JobStatus;
use App\Models\Job;
use RuntimeException;

class JobNotAcceptingLineItems extends RuntimeException
{
    public readonly JobStatus $status;

    public function __construct(
        public readonly Job $job,
    ) {
        $this->status = $job->status;

        parent::__construct(
            "Job {$job->getKey()} is {$this->status->value} and no longer accepts line item writes."
        );
    }
}
