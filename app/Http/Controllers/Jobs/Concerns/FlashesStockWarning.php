<?php

namespace App\Http\Controllers\Jobs\Concerns;

use App\Models\JobItem;

trait FlashesStockWarning
{
    /**
     * @return array<string, string>
     */
    protected function stockWarning(JobItem $item): array
    {
        if (! $item->relationLoaded('part') || ! $item->part->hasNegativeStock()) {
            return [];
        }

        return [
            'warning' => __('jobs.items.flash.stock_negative', [
                'name' => $item->part->name,
                'balance' => $item->part->quantity_in_stock,
            ]),
        ];
    }
}
