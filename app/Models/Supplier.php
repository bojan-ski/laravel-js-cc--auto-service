<?php

namespace App\Models;

use App\Services\IdentifierNormalizer;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            $supplier->name_normalized = IdentifierNormalizer::normalize($supplier->name);
        });
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    #[Scope]
    protected function named(Builder $query, string $name): void
    {
        $query->where('name_normalized', IdentifierNormalizer::normalize($name));
    }
}
