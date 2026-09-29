<?php

namespace App\Models;

use App\Enums\JobItemType;
use App\Exceptions\JobLockedException;
use App\Services\Money;
use Database\Factories\JobItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['description', 'quantity'])]
class JobItem extends Model
{
    /** @use HasFactory<JobItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'discount_percent' => '0.00',
    ];

    protected static function booted(): void
    {
        static::saving(function (JobItem $item): void {
            self::refuseWhenJobIsLocked($item, $item->getDirty());
        });

        static::deleting(function (JobItem $item): void {
            self::refuseWhenJobIsLocked($item, ['job_item' => $item->getKey()]);
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
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isFrontOffice()) {
            return;
        }

        $query->select([
            'id',
            'job_id',
            'type',
            'part_id',
            'user_id',
            'description',
            'quantity',
            'created_at',
            'updated_at',
        ]);
    }

    public function isLabor(): bool
    {
        return $this->type === JobItemType::Labor;
    }

    public function isPart(): bool
    {
        return $this->type === JobItemType::Part;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id !== null && $this->user_id === $user->getKey();
    }

    public function grossTotal(): string
    {
        return Money::round(bcmul($this->quantity, $this->unit_price, 4));
    }

    public function lineTotal(): string
    {
        $gross = bcmul($this->quantity, $this->unit_price, 4);
        $remaining = bcmul($gross, bcsub('100', $this->discount_percent, 2), 6);

        return Money::round(bcdiv($remaining, '100', 8));
    }

    public function discountAmount(): string
    {
        return bcsub($this->grossTotal(), $this->lineTotal(), 2);
    }

    public function hasDiscount(): bool
    {
        return bccomp($this->discount_percent, '0', 2) > 0;
    }

    public function discountPercentLabel(): string
    {
        $trimmed = rtrim(rtrim($this->discount_percent, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function refuseWhenJobIsLocked(JobItem $item, array $changes): void
    {
        $lockedAt = DB::table('jobs')->where('id', $item->job_id)->value('locked_at');

        if ($lockedAt === null) {
            return;
        }

        throw new JobLockedException(Job::query()->findOrFail($item->job_id), $changes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JobItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
        ];
    }
}
