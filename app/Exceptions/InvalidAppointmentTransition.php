<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use RuntimeException;

class InvalidAppointmentTransition extends RuntimeException
{
    public readonly AppointmentStatus $status;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $transition,
    ) {
        $this->status = $appointment->status;

        parent::__construct(
            "Cannot {$transition} appointment {$appointment->getKey()} from status {$this->status->value}."
        );
    }
}
