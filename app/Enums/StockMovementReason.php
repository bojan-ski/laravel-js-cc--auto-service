<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case JobUse = 'job_use';
    case JobReturn = 'job_return';
    case Restock = 'restock';
    case ManualAdjustment = 'manual_adjustment';

    public function label(): string
    {
        return __('parts.reasons.'.$this->value);
    }

    public function requiredSign(): int
    {
        return match ($this) {
            self::JobUse => -1,
            self::JobReturn, self::Restock => 1,
            self::ManualAdjustment => 0,
        };
    }

    public function isRecordableByHand(): bool
    {
        return match ($this) {
            self::Restock, self::ManualAdjustment => true,
            self::JobUse, self::JobReturn => false,
        };
    }

    public function permits(int $quantityChange): bool
    {
        if ($quantityChange === 0) {
            return false;
        }

        return $this->requiredSign() === 0
            || $this->requiredSign() === ($quantityChange <=> 0);
    }

    /**
     * @return list<self>
     */
    public static function recordableByHand(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $reason): bool => $reason->isRecordableByHand(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function recordableByHandValues(): array
    {
        return array_map(
            fn (self $reason): string => $reason->value,
            self::recordableByHand(),
        );
    }
}
