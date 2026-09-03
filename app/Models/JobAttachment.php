<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Database\Factories\JobAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[Fillable([])]
class JobAttachment extends Model
{
    /** @use HasFactory<JobAttachmentFactory> */
    use HasFactory;

    public const FILENAME_DISPLAY_LENGTH = 40;

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function isPhoto(): bool
    {
        return $this->type === AttachmentType::Photo;
    }

    public function isDocument(): bool
    {
        return $this->type === AttachmentType::Document;
    }

    public function isUploadedBy(User $user): bool
    {
        return $this->uploaded_by === $user->getKey();
    }

    public function pathFor(string $variant): ?string
    {
        return match ($variant) {
            'original' => $this->path,
            'full' => $this->variant_full_path,
            'thumb' => $this->variant_thumb_path,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function storedPaths(): array
    {
        return array_values(array_filter([
            $this->path,
            $this->variant_full_path,
            $this->variant_thumb_path,
        ]));
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, maxPrecision: 1);
    }

    public function displayFilename(): string
    {
        $name = $this->original_filename;

        if (mb_strlen($name) <= self::FILENAME_DISPLAY_LENGTH) {
            return $name;
        }

        $tail = mb_substr($name, -12);
        $head = mb_substr($name, 0, self::FILENAME_DISPLAY_LENGTH - mb_strlen($tail) - 1);

        return $head.'…'.$tail;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttachmentType::class,
            'size_bytes' => 'integer',
        ];
    }
}
