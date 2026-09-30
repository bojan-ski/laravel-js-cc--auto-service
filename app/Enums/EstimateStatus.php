<?php

namespace App\Enums;

use App\Contracts\ProvidesStatusPresentation;

enum EstimateStatus: string implements ProvidesStatusPresentation
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Superseded = 'superseded';

    public function label(): string
    {
        return __('estimates.statuses.'.$this->value);
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Pending, self::Approved => true,
            self::Declined, self::Superseded => false,
        };
    }

    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Approved];
    }

    public function colorToken(): string
    {
        return match ($this) {
            self::Pending => 'status-pending',
            self::Approved => 'status-approved',
            self::Declined => 'status-declined',
            self::Superseded => 'status-superseded',
        };
    }
}
