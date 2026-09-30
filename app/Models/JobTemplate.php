<?php

namespace App\Models;

use App\Enums\JobItemType;
use Database\Factories\JobTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'description'])]
class JobTemplate extends Model
{
    /** @use HasFactory<JobTemplateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<JobTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(JobTemplateItem::class);
    }

    public function hasItems(): bool
    {
        return $this->items()->exists();
    }

    public function hasLaborItems(): bool
    {
        return $this->items()->where('type', JobItemType::Labor)->exists();
    }
}
