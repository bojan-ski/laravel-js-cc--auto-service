<?php

namespace App\Enums;

enum SignatureMethod: string
{
    case Drawn = 'drawn';
    case Paper = 'paper';

    public function label(): string
    {
        return __('check_ins.signature_methods.'.$this->value);
    }

    public function summaryLabel(): string
    {
        return match ($this) {
            self::Drawn => __('check_ins.summary.methods.drawn'),
            self::Paper => __('check_ins.summary.methods.paper'),
        };
    }
}
