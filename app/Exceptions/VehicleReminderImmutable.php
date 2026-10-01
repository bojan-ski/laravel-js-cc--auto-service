<?php

namespace App\Exceptions;

use App\Models\VehicleReminder;
use RuntimeException;

class VehicleReminderImmutable extends RuntimeException
{
    public function __construct(
        public readonly VehicleReminder $reminder,
        string $because,
    ) {
        parent::__construct(
            "Refused to change vehicle reminder {$reminder->getKey()}: {$because}."
        );
    }
}
