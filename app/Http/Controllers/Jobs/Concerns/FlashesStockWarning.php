<?php

namespace App\Http\Controllers\Jobs\Concerns;

use App\Models\JobItem;
use App\Models\Part;
use Illuminate\Support\Collection;

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

    /**
     * @param  Collection<int, JobItem>  $items
     * @return array<string, string>
     */
    protected function templateStockWarning(Collection $items): array
    {
        $negative = $items
            ->filter(fn (JobItem $item): bool => $item->relationLoaded('part'))
            ->map(fn (JobItem $item): Part => $item->part)
            ->unique(fn (Part $part): int => $part->getKey())
            ->filter(fn (Part $part): bool => $part->hasNegativeStock());

        if ($negative->isEmpty()) {
            return [];
        }

        return [
            'warning' => __('templates.apply.flash.stock_negative', [
                'parts' => $negative
                    ->map(fn (Part $part): string => __('templates.apply.flash.stock_negative_part', [
                        'name' => $part->name,
                        'balance' => $part->quantity_in_stock,
                    ]))
                    ->implode(', '),
            ]),
        ];
    }
}
