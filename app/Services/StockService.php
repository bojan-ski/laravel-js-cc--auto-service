<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Exceptions\InvalidStockMovement;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    public const int HISTORY_LIMIT = 20;

    public function record(
        Part $part,
        int $quantityChange,
        StockMovementReason $reason,
        User $causedBy,
        ?JobItem $jobItem = null,
        ?string $note = null,
    ): StockMovement {
        if ($quantityChange === 0) {
            throw new InvalidStockMovement($part, $quantityChange, $reason, 'a movement must change the count');
        }

        if (! $reason->permits($quantityChange)) {
            throw new InvalidStockMovement($part, $quantityChange, $reason, 'the sign contradicts the reason');
        }

        return DB::transaction(function () use ($part, $quantityChange, $reason, $causedBy, $jobItem, $note): StockMovement {
            $locked = Part::withTrashed()->whereKey($part->getKey())->lockForUpdate()->firstOrFail();

            $locked->quantity_in_stock = $locked->quantity_in_stock + $quantityChange;
            $locked->save();

            $movement = new StockMovement;
            $movement->part_id = $locked->getKey();
            $movement->job_item_id = $jobItem?->getKey();
            $movement->user_id = $causedBy->getKey();
            $movement->quantity_change = $quantityChange;
            $movement->reason = $reason;
            $movement->note = $note;
            $movement->save();

            $part->setRawAttributes([
                ...$part->getAttributes(),
                'quantity_in_stock' => $locked->quantity_in_stock,
            ], true);

            return $movement;
        });
    }

    /**
     * @return Collection<int, StockMovement>
     */
    public function historyFor(Part $part): Collection
    {
        return $part->stockMovements()
            ->with(['causedBy', 'jobItem:id,job_id', 'jobItem.job:id,reference'])
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }

    public function movementCountFor(Part $part): int
    {
        return $part->stockMovements()->count();
    }
}
