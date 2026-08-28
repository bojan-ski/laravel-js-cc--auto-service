<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Job $job): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can start work on the job.
     */
    public function start(User $user, Job $job): bool
    {
        return $job->canStart();
    }

    /**
     * Determine whether the user can pause the job for parts.
     */
    public function pause(User $user, Job $job): bool
    {
        return $job->canPause();
    }

    /**
     * Determine whether the user can resume a paused job.
     */
    public function resume(User $user, Job $job): bool
    {
        return $job->canResume();
    }

    /**
     * Determine whether the user can complete the job.
     */
    public function complete(User $user, Job $job): bool
    {
        return $job->canComplete();
    }

    /**
     * Determine whether the user can add a line item to the job.
     */
    public function createItem(User $user, Job $job): bool
    {
        return $job->isWorking() && ! $job->isLocked();
    }

    /**
     * Determine whether the user can see prices, line totals, and job totals.
     */
    public function viewPricing(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can cancel the job.
     */
    public function cancel(User $user, Job $job): bool
    {
        return $user->isFrontOffice() && $job->canCancel();
    }

    /**
     * Determine whether the user can reopen a completed job.
     */
    public function reopen(User $user, Job $job): bool
    {
        return $user->isFrontOffice() && $job->canReopen();
    }
}
