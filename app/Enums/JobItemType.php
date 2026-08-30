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

    /**
     * Part quantities are whole numbers because stock_movements.quantity_change is
     * an integer; labor keeps the decimals because 90 minutes is 1.5 hours. This is
     * the single source for that rule — the Form Requests and both forms read it.
     *
     * @return list<string>
     */
    public function quantityRules(): array
    {
        return match ($this) {
            self::Labor => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            self::Part => ['required', 'integer', 'min:1', 'max:999999'],
        };
    }

    public function quantityStep(): string
    {
        return $this === self::Part ? '1' : '0.01';
    }

    public function quantityMin(): string
    {
        return $this === self::Part ? '1' : '0.01';
    }

    public function formatQuantity(string $quantity): string
    {
        return $this === self::Part ? (string) (int) $quantity : $quantity;
    }
}
