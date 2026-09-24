<?php

namespace App\Enums;

use App\Models\User;

enum Theme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public function label(): string
    {
        return __('app.theme.'.$this->value);
    }

    public static function forUser(?User $user): self
    {
        return $user?->theme ?? self::System;
    }
}
