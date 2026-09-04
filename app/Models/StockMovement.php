<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Exceptions\InvalidStockMovement;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (StockMovement $movement): void {
            self::refuse($movement, 'the ledger is append-only and a movement is never updated');
        });

        static::deleting(function (StockMovement $movement): void {
            self::refuse($movement, 'the ledger is append-only and a movement is never deleted');
        });
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    /**
     * @return BelongsTo<JobItem, $this>
     */
    public function jobItem(): BelongsTo
    {
        return $this->belongsTo(JobItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function causedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isIncrease(): bool
    {
        return $this->quantity_change > 0;
    }

    private static function refuse(StockMovement $movement, string $because): void
    {
        throw new InvalidStockMovement(
            Part::withTrashed()->findOrFail($movement->part_id),
            (int) $movement->quantity_change,
            $movement->reason,
            $because,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => StockMovementReason::class,
        ];
    }
}
