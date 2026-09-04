<?php

namespace App\Services;

use App\Enums\PartNumberType;
use App\Models\Part;
use App\Models\PartNumber;
use Illuminate\Database\Eloquent\Builder;

class PartNumberService
{
    public function add(Part $part, PartNumberType $type, string $value): PartNumber
    {
        $number = new PartNumber;

        $number->part_id = $part->getKey();
        $number->fill(['type' => $type, 'number' => trim($value)]);
        $number->save();

        return $number;
    }

    public function remove(PartNumber $number): void
    {
        $number->delete();
    }

    /**
     * The other part carrying this catalogue number under the same brand, if
     * there is one. Drives the non-blocking warning; never refuses a write.
     */
    public function catalogueConflict(Part $part, string $value): ?Part
    {
        if ($part->brand_id === null) {
            return null;
        }

        return Part::query()
            ->select(['id', 'sku', 'name'])
            ->where('brand_id', $part->brand_id)
            ->whereKeyNot($part->getKey())
            ->whereHas(
                'numbers',
                fn (Builder $numbers) => $numbers->ofType(PartNumberType::Catalog)->normalizedAs($value),
            )
            ->first();
    }
}
