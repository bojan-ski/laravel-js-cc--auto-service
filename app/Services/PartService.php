<?php

namespace App\Services;

use App\Models\Part;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PartService
{
    public const int PER_PAGE = 25;

    public const int LOW_STOCK_LIMIT = 12;

    /**
     * @var list<string>
     */
    private const LOW_STOCK_COLUMNS = ['id', 'name', 'quantity_in_stock', 'reorder_level'];

    /**
     * @param  array{search: string, filter: string, trashed: string}  $filters
     * @return LengthAwarePaginator<int, Part>
     */
    public function paginateFiltered(array $filters, User $user, bool $withTrashed): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $withTrashed)
            ->visibleTo($user)
            ->with('brand:id,name,quality_tier,deleted_at')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array{search: string, filter: string, trashed: string}  $filters
     * @return array{all: int, low_stock: int}
     */
    public function counts(array $filters, bool $withTrashed): array
    {
        $search = ['search' => $filters['search'], 'filter' => '', 'trashed' => $filters['trashed']];

        return [
            'all' => $this->baseQuery($search, $withTrashed)->count(),
            'low_stock' => $this->baseQuery($search, $withTrashed)->lowStock()->count(),
        ];
    }

    /**
     * Distinct categories already in use, for the form's datalist.
     *
     * @return list<string>
     */
    public function categoryOptions(): array
    {
        return Part::query()
            ->withTrashed()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function hasAny(bool $withTrashed = false): bool
    {
        return Part::query()->when($withTrashed, fn (Builder $query) => $query->withTrashed())->exists();
    }

    /**
     * @return Collection<int, Part>
     */
    public function lowStock(): Collection
    {
        return Part::query()
            ->lowStock()
            ->select(self::LOW_STOCK_COLUMNS)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LOW_STOCK_LIMIT)
            ->get();
    }

    public function countLowStock(): int
    {
        return Part::query()->lowStock()->count();
    }

    /**
     * @return array<int, string>
     */
    public function pickerOptions(User $user): array
    {
        return Part::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Part $part): array => [$part->getKey() => $this->pickerLabel($part)])
            ->all();
    }

    private function pickerLabel(Part $part): string
    {
        $label = __('parts.picker.option', [
            'sku' => $part->sku,
            'name' => $part->name,
            'stock' => $part->quantity_in_stock,
        ]);

        if ($part->location !== null) {
            $label = __('parts.picker.shelf', ['label' => $label, 'location' => $part->location]);
        }

        if ($part->isOutOfStock()) {
            return __('parts.picker.out_of_stock', ['label' => $label]);
        }

        return $part->isLowStock() ? __('parts.picker.low_stock', ['label' => $label]) : $label;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Part
    {
        $part = new Part;

        $part->fill($attributes);
        $part->quantity_in_stock = 0;
        $part->save();

        return $part;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Part $part, array $attributes): Part
    {
        $part->update($attributes);

        return $part;
    }

    public function delete(Part $part): void
    {
        $part->delete();
    }

    public function restore(Part $part): Part
    {
        $part->restore();

        return $part;
    }

    /**
     * @param  array{search: string, filter: string, trashed: string}  $filters
     * @return Builder<Part>
     */
    private function baseQuery(array $filters, bool $withTrashed): Builder
    {
        return Part::query()
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->when($filters['search'], fn (Builder $query, string $term) => $query->matching($term))
            ->when($filters['filter'] === 'low_stock', fn (Builder $query) => $query->lowStock());
    }
}
