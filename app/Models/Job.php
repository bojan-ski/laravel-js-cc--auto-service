<?php

namespace App\Models;

use App\Enums\AttachmentType;
use App\Enums\InvoiceStatus;
use App\Enums\JobItemType;
use App\Enums\JobStatus;
use App\Exceptions\JobLockedException;
use App\Models\Concerns\HasHistoricalOwnership;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

#[Fillable(['complaint', 'mileage_in'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    use HasHistoricalOwnership;

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
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return HasMany<JobItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(JobItem::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function activeInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('status', '!=', InvoiceStatus::Void->value);
    }

    /**
     * @return HasMany<JobItem, $this>
     */
    public function laborItems(): HasMany
    {
        return $this->hasMany(JobItem::class)->where('type', JobItemType::Labor);
    }

    /**
     * @return HasMany<JobItem, $this>
     */
    public function partItems(): HasMany
    {
        return $this->hasMany(JobItem::class)->where('type', JobItemType::Part)->orderBy('id');
    }

    /**
     * @return HasMany<JobAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(JobAttachment::class);
    }

    /**
     * @return HasMany<JobAttachment, $this>
     */
    public function photos(): HasMany
    {
        return $this->attachments()->where('type', AttachmentType::Photo)->orderBy('id');
    }

    /**
     * @return HasMany<JobAttachment, $this>
     */
    public function documents(): HasMany
    {
        return $this->attachments()->where('type', AttachmentType::Document)->orderBy('id');
    }

    #[Scope]
    protected function joinedForBoard(Builder $query): void
    {
        $query
            ->join('vehicles', 'vehicles.id', '=', 'jobs.vehicle_id')
            ->join('customers', 'customers.id', '=', 'jobs.customer_id')
            ->select('jobs.*');
    }

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');

        $query->where(function (Builder $query) use ($escaped): void {
            $query->where('vehicles.registration_plate', 'like', "%{$escaped}%")
                ->orWhere('customers.name', 'like', "%{$escaped}%");
        });
    }

    #[Scope]
    protected function workedOnBy(Builder $query, User $user): void
    {
        $query->whereRelation('laborItems', 'user_id', $user->getKey());
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
        return $this->status === JobStatus::InProgress && ! $this->isLocked() && $this->hasLineItems();
    }

    public function canCancel(): bool
    {
        return $this->isWorking() && ! $this->isLocked();
    }

    public function canReopen(): bool
    {
        return $this->status === JobStatus::Completed && ! $this->isLocked();
    }

    public function acceptsDiscounts(): bool
    {
        return ! $this->isLocked()
            && ($this->isWorking() || $this->status === JobStatus::Completed);
    }

    public function canBeInvoiced(): bool
    {
        return $this->status === JobStatus::Completed
            && ! $this->isLocked()
            && $this->hasLineItems()
            && ! $this->hasActiveInvoice();
    }

    public function hasActiveInvoice(): bool
    {
        return $this->activeInvoice()->exists();
    }

    public function needsLineItemsToComplete(): bool
    {
        return $this->status === JobStatus::InProgress
            && ! $this->isLocked()
            && ! $this->hasLineItems();
    }

    public function hasLineItems(): bool
    {
        return $this->relationLoaded('items')
            ? $this->items->isNotEmpty()
            : $this->items()->exists();
    }

    public function partLineCount(): int
    {
        return $this->relationLoaded('items')
            ? $this->items->where('type', JobItemType::Part)->count()
            : $this->partItems()->count();
    }

    public function subtotal(): string
    {
        return $this->items->reduce(
            fn (string $carry, JobItem $item): string => bcadd($carry, $item->lineTotal(), 2),
            '0.00',
        );
    }

    public function discountTotal(): string
    {
        return $this->items->reduce(
            fn (string $carry, JobItem $item): string => bcadd($carry, $item->discountAmount(), 2),
            '0.00',
        );
    }

    /**
     * @return Collection<int, string>
     */
    public function assignedNames(): Collection
    {
        return $this->laborItems
            ->map(fn (JobItem $item): ?string => $item->user?->shortName())
            ->filter()
            ->unique()
            ->values();
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
