<?php

namespace App\Services;

use App\Enums\JobItemType;
use App\Enums\StockMovementReason;
use App\Exceptions\JobLockedException;
use App\Exceptions\JobNotAcceptingLineItems;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class JobItemService
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addLabor(Job $job, User $actor, array $attributes): JobItem
    {
        return DB::transaction(function () use ($job, $actor, $attributes): JobItem {
            $this->lockWritableJob($job->getKey());

            $item = new JobItem;
            $item->fill($attributes);

            $item->job_id = $job->getKey();
            $item->type = JobItemType::Labor;
            $item->part_id = null;
            $item->user_id = $this->assignedUserId($actor, $attributes);
            $item->unit_price = $this->submittedRate($actor, $attributes) ?? (string) config('shop.labor_rate');

            $item->save();

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addPart(Job $job, Part $part, User $actor, array $attributes): JobItem
    {
        return DB::transaction(function () use ($job, $part, $actor, $attributes): JobItem {
            $this->lockWritableJob($job->getKey());

            $quantity = (int) $attributes['quantity'];

            $item = new JobItem;

            $item->job_id = $job->getKey();
            $item->type = JobItemType::Part;
            $item->part_id = $part->getKey();
            $item->user_id = $this->assignedUserId($actor, $attributes);
            $item->description = $this->submittedDescription($attributes) ?? $part->name;
            $item->quantity = $quantity;
            $item->unit_price = $this->submittedRate($actor, $attributes) ?? $this->catalogPrice($part);

            $item->save();

            $this->stock->record($part, -$quantity, StockMovementReason::JobUse, $actor, $item);

            $item->setRelation('part', $part);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(JobItem $item, User $actor, array $attributes): JobItem
    {
        return DB::transaction(function () use ($item, $actor, $attributes): JobItem {
            $this->lockWritableJob($item->job_id);

            $previousQuantity = (int) $item->getOriginal('quantity');

            $item->fill($attributes);

            if ($item->isPart()) {
                $item->description = $this->submittedDescription($attributes) ?? $item->getOriginal('description');
            }

            if ($actor->isFrontOffice()) {
                $item->user_id = $this->assignedUserId($actor, $attributes);
                $item->unit_price = $this->submittedRate($actor, $attributes) ?? $item->unit_price;
            }

            $item->save();

            if ($item->isPart()) {
                $this->applyQuantityDelta($item, $actor, $previousQuantity);
            }

            return $item;
        });
    }

    public function delete(JobItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $this->lockWritableJob($item->job_id);

            if ($item->isPart()) {
                $part = $this->resolvePart($item, $actor);

                $this->stock->record(
                    $part,
                    (int) $item->quantity,
                    StockMovementReason::JobReturn,
                    $actor,
                    $item,
                );
            }

            $item->delete();
        });
    }

    public function setDiscount(JobItem $item, string $percent): JobItem
    {
        return DB::transaction(function () use ($item, $percent): JobItem {
            $job = Job::query()->whereKey($item->job_id)->lockForUpdate()->firstOrFail();

            if ($job->isLocked()) {
                throw new JobLockedException($job, ['discount_percent' => $percent]);
            }

            if (! $job->acceptsDiscounts()) {
                throw new JobNotAcceptingLineItems($job);
            }

            $item->discount_percent = $percent;
            $item->save();

            return $item;
        });
    }

    private function lockWritableJob(int $jobId): Job
    {
        $job = Job::query()->whereKey($jobId)->lockForUpdate()->firstOrFail();

        if ($job->isLocked()) {
            throw new JobLockedException($job, []);
        }

        if (! $job->isWorking()) {
            throw new JobNotAcceptingLineItems($job);
        }

        return $job;
    }

    private function applyQuantityDelta(JobItem $item, User $actor, int $previousQuantity): void
    {
        $change = $previousQuantity - (int) $item->quantity;

        if ($change === 0) {
            return;
        }

        $part = $this->resolvePart($item, $actor);

        $this->stock->record(
            $part,
            $change,
            $change < 0 ? StockMovementReason::JobUse : StockMovementReason::JobReturn,
            $actor,
            $item,
        );

        $item->setRelation('part', $part);
    }

    private function resolvePart(JobItem $item, User $actor): Part
    {
        return Part::withTrashed()->visibleTo($actor)->whereKey($item->part_id)->firstOrFail();
    }

    private function catalogPrice(Part $part): string
    {
        return (string) Part::withTrashed()->whereKey($part->getKey())->value('unit_price');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignedUserId(User $actor, array $attributes): ?int
    {
        if (! $actor->isFrontOffice()) {
            return $actor->getKey();
        }

        $userId = $attributes['user_id'] ?? null;

        return $userId === null || $userId === '' ? null : (int) $userId;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function submittedDescription(array $attributes): ?string
    {
        $description = $attributes['description'] ?? null;

        return $description === null || $description === '' ? null : (string) $description;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function submittedRate(User $actor, array $attributes): ?string
    {
        if (! $actor->isFrontOffice()) {
            return null;
        }

        $rate = $attributes['unit_price'] ?? null;

        return $rate === null || $rate === '' ? null : (string) $rate;
    }
}
