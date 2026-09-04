<?php

namespace App\Models;

use App\Enums\PartNumberType;
use App\Services\IdentifierNormalizer;
use Database\Factories\PartNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'number'])]
class PartNumber extends Model
{
    /** @use HasFactory<PartNumberFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (PartNumber $number): void {
            $number->number_normalized = IdentifierNormalizer::normalize($number->number);
        });
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    #[Scope]
    protected function normalizedAs(Builder $query, string $number): void
    {
        $query->where('number_normalized', IdentifierNormalizer::normalize($number));
    }

    #[Scope]
    protected function ofType(Builder $query, PartNumberType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PartNumberType::class,
        ];
    }
}
