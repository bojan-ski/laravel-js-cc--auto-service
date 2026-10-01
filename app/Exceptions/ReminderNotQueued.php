<?php

namespace App\Exceptions;

use App\Models\Customer;
use RuntimeException;

class ReminderNotQueued extends RuntimeException
{
    public function __construct(
        public readonly Customer $customer,
    ) {
        parent::__construct(
            "Did not queue a reminder to customer {$customer->getKey()}: there is no email address on file."
        );
    }
}
