<?php

namespace App\Models;

use App\Enums\QualityTier;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'quality_tier'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<Part, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');

        $query->where('name', 'like', "%{$escaped}%");
    }

    #[Scope]
    protected function named(Builder $query, string $name): void
    {
        $query->where('name', trim($name));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quality_tier' => QualityTier::class,
        ];
    }
}
