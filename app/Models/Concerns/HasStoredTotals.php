<?php

namespace App\Models\Concerns;

use App\Services\Money;

trait HasStoredTotals
{
    public function hasDiscount(): bool
    {
        return bccomp($this->discount_total, '0', 2) > 0;
    }

    public function taxRateLabel(): string
    {
        return Money::trimZeros($this->tax_rate);
    }

    /**
     * @return array{subtotal: string, rate: string, tax: string, total: string, discount: string, before_discount: string, has_discount: bool}
     */
    public function storedTotals(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'rate' => $this->taxRateLabel(),
            'tax' => $this->tax_amount,
            'total' => $this->total,
            'discount' => $this->discount_total,
            'before_discount' => bcadd($this->subtotal, $this->discount_total, 2),
            'has_discount' => $this->hasDiscount(),
        ];
    }
}
