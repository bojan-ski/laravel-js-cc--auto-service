<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function resolveByName(?string $name): ?Supplier
    {
        $normalized = IdentifierNormalizer::normalize($name);

        if ($normalized === '') {
            return null;
        }

        return DB::transaction(function () use ($name): Supplier {
            $existing = Supplier::query()
                ->named((string) $name)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $supplier = new Supplier;
            $supplier->fill(['name' => trim((string) $name)]);
            $supplier->save();

            return $supplier;
        });
    }

    /**
     * @return list<string>
     */
    public function pickerOptions(): array
    {
        return Supplier::query()->orderBy('name')->pluck('name')->all();
    }
}
