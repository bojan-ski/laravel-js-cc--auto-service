<?php

namespace App\Services;

use App\Models\Part;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PartService
{
    public const int PER_PAGE = 25;

    /**
     * @param  array{search: string, filter: string, trashed: string}  $filters
     * @return LengthAwarePaginator<int, Part>
     */
    public function paginateFiltered(array $filters, User $user, bool $withTrashed): LengthAwarePaginator
    {
        return $this->baseQuery($filters, $withTrashed)
            ->visibleTo($user)
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

    public function hasAny(bool $withTrashed = false): bool
    {
        return Part::query()->when($withTrashed, fn (Builder $query) => $query->withTrashed())->exists();
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
