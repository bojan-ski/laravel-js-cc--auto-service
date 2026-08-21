<?php

namespace App\Enums;

use App\Contracts\ProvidesStatusPresentation;

enum AppointmentStatus: string implements ProvidesStatusPresentation
{
    case Booked = 'booked';
    case Arrived = 'arrived';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('appointments.statuses.'.$this->value);
    }

    public function isTerminal(): bool
    {
        return $this !== self::Booked;
    }

    public function colorToken(): string
    {
        return match ($this) {
            self::Booked => 'status-booked',
            self::Arrived => 'status-arrived',
            self::NoShow => 'status-no-show',
            self::Cancelled => 'status-cancelled',
        };
    }
}
