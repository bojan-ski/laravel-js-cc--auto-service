<?php

namespace App\Models;

use App\Enums\DecisionMethod;
use App\Enums\EstimateStatus;
use App\Exceptions\InvalidEstimateTransition;
use Database\Factories\EstimateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
class Estimate extends Model
{
    /** @use HasFactory<EstimateFactory> */
    use HasFactory;

    public const array MUTABLE = [
        'status',
        'decision_method',
        'decided_at',
        'decided_by',
        'decision_note',
        'superseded_at',
        'emailed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::saving(fn (Estimate $estimate) => $estimate->refuseFrozenWrite());

        static::deleting(function (Estimate $estimate): void {
            throw new InvalidEstimateTransition($estimate, [], 'an estimate is never deleted');
        });
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return HasMany<EstimateLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EstimateLine::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === EstimateStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === EstimateStatus::Approved;
    }

    public function isDeclined(): bool
    {
        return $this->status === EstimateStatus::Declined;
    }

    public function isSuperseded(): bool
    {
        return $this->status === EstimateStatus::Superseded;
    }

    public function wasDecided(): bool
    {
        return $this->decided_at !== null;
    }

    public function canBeDecided(): bool
    {
        return $this->isPending();
    }

    public function canBeEmailed(): bool
    {
        return $this->status->isOpen();
    }

    public function hasDiscount(): bool
    {
        return bccomp($this->discount_total, '0', 2) > 0;
    }

    public function taxRateLabel(): string
    {
        $trimmed = rtrim(rtrim($this->tax_rate, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    private function refuseFrozenWrite(): void
    {
        if (! $this->exists) {
            return;
        }

        $changes = $this->getDirty();

        if (! array_key_exists('status', $this->getRawOriginal())) {
            throw new InvalidEstimateTransition($this, $changes, 'the stored status was not read, so the frozen guard cannot be applied');
        }

        if ($changes === []) {
            return;
        }

        if ($this->getOriginal('status')->isTerminal()) {
            throw new InvalidEstimateTransition($this, $changes, 'a declined or superseded estimate is final');
        }

        $frozen = array_diff_key($changes, array_flip(self::MUTABLE));

        if ($frozen !== []) {
            throw new InvalidEstimateTransition($this, $frozen, 'an estimate is a frozen copy');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EstimateStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'decision_method' => DecisionMethod::class,
            'decided_at' => 'datetime',
            'superseded_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }
}
