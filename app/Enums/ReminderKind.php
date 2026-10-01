<?php

namespace App\Enums;

enum ReminderKind: string
{
    case Service = 'service';
    case Registration = 'registration';

    public function label(): string
    {
        return __('vehicles.reminder_kinds.'.$this->value);
    }

    public function dateColumn(): string
    {
        return match ($this) {
            self::Service => 'next_service_on',
            self::Registration => 'registration_expires_on',
        };
    }
}
