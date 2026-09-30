<?php

namespace App\Models;

use App\Enums\JobItemType;
use App\Exceptions\InvalidEstimateTransition;
use App\Services\Money;
use Database\Factories\EstimateLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class EstimateLine extends Model
{
    /** @use HasFactory<EstimateLineFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (EstimateLine $line): void {
            throw new InvalidEstimateTransition($line, $line->getDirty(), 'an estimate line is a frozen copy');
        });

        static::deleting(function (EstimateLine $line): void {
            throw new InvalidEstimateTransition($line, [], 'an estimate line is never deleted');
        });
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function isLabor(): bool
    {
        return $this->type === JobItemType::Labor;
    }

    public function hasDiscount(): bool
    {
        return bccomp($this->discount_percent, '0', 2) > 0;
    }

    public function discountPercentLabel(): string
    {
        return Money::trimZeros($this->discount_percent);
    }

    public function lineTotal(): string
    {
        return $this->line_total;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JobItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
            'position' => 'integer',
        ];
    }
}
