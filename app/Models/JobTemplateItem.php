<?php

namespace App\Models;

use App\Enums\JobItemType;
use Database\Factories\JobTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['description', 'quantity'])]
class JobTemplateItem extends Model
{
    /** @use HasFactory<JobTemplateItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<JobTemplate, $this>
     */
    public function jobTemplate(): BelongsTo
    {
        return $this->belongsTo(JobTemplate::class);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function isLabor(): bool
    {
        return $this->type === JobItemType::Labor;
    }

    public function isPart(): bool
    {
        return $this->type === JobItemType::Part;
    }

    public function hasDeletedPart(): bool
    {
        return $this->isPart() && $this->part?->trashed() === true;
    }

    public function displayName(): string
    {
        return $this->isPart() ? (string) $this->part?->name : (string) $this->description;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JobItemType::class,
            'quantity' => 'decimal:2',
        ];
    }
}
