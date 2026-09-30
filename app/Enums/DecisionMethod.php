<?php

namespace App\Enums;

enum DecisionMethod: string
{
    case InPerson = 'in_person';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return __('estimates.decision_methods.'.$this->value);
    }
}
