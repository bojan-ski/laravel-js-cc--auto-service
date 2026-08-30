<?php

namespace App\Enums;

use App\Contracts\ProvidesStatusPresentation;

enum InvoiceStatus: string implements ProvidesStatusPresentation
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return __('invoices.statuses.'.$this->value);
    }

    public function isVoid(): bool
    {
        return $this === self::Void;
    }

    public function colorToken(): string
    {
        return match ($this) {
            self::Unpaid => 'status-unpaid',
            self::Paid => 'status-paid',
            self::Void => 'status-void',
        };
    }
}
