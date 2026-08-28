<?php

namespace App\Enums;

enum JobItemType: string
{
    case Labor = 'labor';
    case Part = 'part';

    public function label(): string
    {
        return __('jobs.items.types.'.$this->value);
    }
}
