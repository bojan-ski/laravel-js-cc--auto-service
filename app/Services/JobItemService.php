<?php

namespace App\Services;

use App\Enums\JobItemType;
use App\Models\Job;
use App\Models\JobItem;
use App\Models\User;

class JobItemService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addLabor(Job $job, User $actor, array $attributes): JobItem
    {
        $item = new JobItem;
        $item->fill($attributes);

        $item->job_id = $job->getKey();
        $item->type = JobItemType::Labor;
        $item->part_id = null;
        $item->user_id = $this->assignedUserId($actor, $attributes);
        $item->unit_price = $this->submittedRate($actor, $attributes) ?? (string) config('shop.labor_rate');

        $item->save();

        return $item;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(JobItem $item, User $actor, array $attributes): JobItem
    {
        $item->fill($attributes);

        if ($actor->isFrontOffice()) {
            $item->user_id = $this->assignedUserId($actor, $attributes);
            $item->unit_price = $this->submittedRate($actor, $attributes) ?? $item->unit_price;
        }

        $item->save();

        return $item;
    }

    public function delete(JobItem $item): void
    {
        $item->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignedUserId(User $actor, array $attributes): int
    {
        return $actor->isFrontOffice()
            ? (int) $attributes['user_id']
            : $actor->getKey();
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
