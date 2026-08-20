<?php

namespace App\Enums;

enum UserRole: string
{
    case Mechanic = 'mechanic';
    case Receptionist = 'receptionist';
    case Admin = 'admin';

    public function label(): string
    {
        return __('app.roles.'.$this->value);
    }

    public function isFrontOffice(): bool
    {
        return $this === self::Receptionist || $this === self::Admin;
    }
}
