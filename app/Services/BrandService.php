<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BrandService
{
    public const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginateFiltered(string $search, bool $withTrashed): LengthAwarePaginator
    {
        return Brand::query()
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->when($search !== '', fn (Builder $query) => $query->matching($search))
            ->withCount(['parts' => fn (Builder $parts) => $parts->withTrashed()])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function hasAny(bool $withTrashed = false): bool
    {
        return Brand::query()
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    public function pickerOptions(): array
    {
        return Brand::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'quality_tier'])
            ->mapWithKeys(fn (Brand $brand): array => [
                $brand->getKey() => __('brands.picker.option', [
                    'name' => $brand->name,
                    'tier' => $brand->quality_tier->label(),
                ]),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Brand
    {
        $brand = new Brand;

        $brand->fill($attributes);
        $brand->save();

        return $brand;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Brand $brand, array $attributes): Brand
    {
        $brand->update($attributes);

        return $brand;
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }

    public function restore(Brand $brand): Brand
    {
        $brand->restore();

        return $brand;
    }
}
