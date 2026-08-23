<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Exceptions\JobLockedException;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

#[Fillable(['complaint', 'mileage_in'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => JobStatus::Received->value,
    ];

    protected static function booted(): void
    {
        static::saving(function (Job $job): void {
            if ($job->exists && ! array_key_exists('locked_at', $job->getRawOriginal())) {
                throw new JobLockedException($job, $job->getDirty());
            }

            if ($job->getOriginal('locked_at') === null) {
                return;
            }

            $changes = Arr::except($job->getDirty(), ['locked_at']);

            if ($changes !== []) {
                throw new JobLockedException($job, $changes);
            }
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isWorking(): bool
    {
        return $this->status->isWorking();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canStart(): bool
    {
        return $this->status === JobStatus::Received && ! $this->isLocked();
    }

    public function canPause(): bool
    {
        return $this->status === JobStatus::InProgress && ! $this->isLocked();
    }

    public function canResume(): bool
    {
        return $this->status === JobStatus::AwaitingParts && ! $this->isLocked();
    }

    public function canComplete(): bool
    {
        return $this->status === JobStatus::InProgress && ! $this->isLocked();
    }

    public function canCancel(): bool
    {
        return $this->isWorking() && ! $this->isLocked();
    }

    public function canReopen(): bool
    {
        return $this->status === JobStatus::Completed && ! $this->isLocked();
    }

    public function mileageInIsBelowVehicleMileage(): bool
    {
        return $this->mileage_in !== null
            && $this->vehicle->mileage !== null
            && $this->mileage_in < $this->vehicle->mileage;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'mileage_in' => 'integer',
            'started_at' => 'datetime',
            'awaiting_parts_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }
}
