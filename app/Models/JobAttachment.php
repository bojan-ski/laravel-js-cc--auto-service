<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use App\Enums\AttachmentType;
use App\Exceptions\CheckInSignedException;
use Database\Factories\JobAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

#[Fillable([])]
class JobAttachment extends Model
{
    /** @use HasFactory<JobAttachmentFactory> */
    use HasFactory;

    public const FILENAME_DISPLAY_LENGTH = 40;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => 'general',
    ];

    protected static function booted(): void
    {
        static::saving(fn (JobAttachment $attachment) => $attachment->refuseWhenCheckInIsSigned());
        static::deleting(fn (JobAttachment $attachment) => $attachment->refuseWhenCheckInIsSigned());
    }

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

    public function isCheckInPhoto(): bool
    {
        return $this->category === AttachmentCategory::CheckIn;
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

    private function refuseWhenCheckInIsSigned(): void
    {
        if (! $this->isCheckInPhoto()) {
            return;
        }

        $signedAt = DB::table('job_check_ins')->where('job_id', $this->job_id)->value('signed_at');

        if ($signedAt !== null) {
            throw new CheckInSignedException($this->job_id, 'a check-in photo');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttachmentType::class,
            'category' => AttachmentCategory::class,
            'size_bytes' => 'integer',
        ];
    }
}
