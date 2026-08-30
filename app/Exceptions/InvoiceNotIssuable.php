<?php

namespace App\Exceptions;

use App\Models\Job;
use RuntimeException;

class InvoiceNotIssuable extends RuntimeException
{
    public const string NOT_COMPLETED = 'not_completed';

    public const string LOCKED = 'locked';

    public const string NO_LINE_ITEMS = 'no_line_items';

    public const string ALREADY_INVOICED = 'already_invoiced';

    public function __construct(
        public readonly Job $job,
        public readonly string $precondition,
    ) {
        parent::__construct(
            "Refused to invoice job {$job->getKey()}: {$precondition}."
        );
    }
}
