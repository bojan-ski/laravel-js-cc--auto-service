<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use App\Enums\FuelLevel;
use App\Enums\SignatureMethod;
use App\Exceptions\CheckInSignedException;
use Database\Factories\JobCheckInFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['fuel_level', 'damage_notes', 'valuables'])]
class JobCheckIn extends Model
{
    /** @use HasFactory<JobCheckInFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (JobCheckIn $checkIn) => $checkIn->refuseWhenSigned());
        static::deleting(fn (JobCheckIn $checkIn) => $checkIn->refuseWhenSigned());
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
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    /**
     * @return HasMany<JobAttachment, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(JobAttachment::class, 'job_id', 'job_id')->where('category', AttachmentCategory::CheckIn);
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    public function hasDrawnSignature(): bool
    {
        return $this->signature_method === SignatureMethod::Drawn && $this->signature_path !== null;
    }

    private function refuseWhenSigned(): void
    {
        if (! $this->exists) {
            return;
        }

        $unread = ! $this->wasRecentlyCreated && ! array_key_exists('signed_at', $this->getRawOriginal());

        if ($unread || $this->getOriginal('signed_at') !== null) {
            throw new CheckInSignedException($this->job_id, 'the check-in sheet');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fuel_level' => FuelLevel::class,
            'signature_method' => SignatureMethod::class,
            'signed_at' => 'datetime',
        ];
    }
}
