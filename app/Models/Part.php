<?php

namespace App\Models;

use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sku', 'name', 'description', 'unit_price', 'cost_price', 'reorder_level', 'location'])]
class Part extends Model
{
    /** @use HasFactory<PartFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    public const array BASE_COLUMNS = [
        'id',
        'sku',
        'name',
        'description',
        'quantity_in_stock',
        'reorder_level',
        'location',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * @var list<string>
     */
    public const array PRICE_COLUMNS = ['unit_price', 'cost_price'];

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('id');
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->select($user->isFrontOffice()
            ? [...self::BASE_COLUMNS, 'unit_price']
            : self::BASE_COLUMNS);
    }

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');

        $query->where(function (Builder $query) use ($escaped) {
            $query->where('sku', 'like', "%{$escaped}%")
                ->orWhere('name', 'like', "%{$escaped}%");
        });
    }

    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->whereColumn('quantity_in_stock', '<=', 'reorder_level');
    }

    public function isLowStock(): bool
    {
        return $this->quantity_in_stock <= $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity_in_stock <= 0;
    }

    public function hasNegativeStock(): bool
    {
        return $this->quantity_in_stock < 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }
}
