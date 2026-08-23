<?php

namespace App\Enums;

use App\Contracts\ProvidesStatusPresentation;

enum JobStatus: string implements ProvidesStatusPresentation
{
    case Received = 'received';
    case InProgress = 'in_progress';
    case AwaitingParts = 'awaiting_parts';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('jobs.statuses.'.$this->value);
    }

    public function isWorking(): bool
    {
        return match ($this) {
            self::Received, self::InProgress, self::AwaitingParts => true,
            self::Completed, self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return ! $this->isWorking();
    }

    public static function sortExpression(): string
    {
        $values = array_map(fn (self $status): string => "'{$status->value}'", self::cases());

        return 'FIELD(jobs.status, '.implode(', ', $values).')';
    }

    public function colorToken(): string
    {
        return match ($this) {
            self::Received => 'status-received',
            self::InProgress => 'status-in-progress',
            self::AwaitingParts => 'status-awaiting-parts',
            self::Completed => 'status-completed',
            self::Cancelled => 'status-cancelled',
        };
    }
}
