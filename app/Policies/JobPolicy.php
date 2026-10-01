<?php

namespace App\Policies;

use App\Enums\JobStatus;
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
        return ($user->isMechanic() || $user->isAdmin()) && $job->canStart();
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
        return ($user->isMechanic() || $user->isAdmin()) && $job->canResume();
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
     * Determine whether the user can attach a photo or document to the job.
     */
    public function upload(User $user, Job $job): bool
    {
        return true;
    }

    /**
     * Determine whether the user can record the check-in sheet or add its photos.
     */
    public function recordCheckIn(User $user, Job $job): bool
    {
        return $job->acceptsCheckIn();
    }

    /**
     * Determine whether the user can sign the check-in sheet.
     */
    public function signCheckIn(User $user, Job $job): bool
    {
        return $job->acceptsCheckInSignature();
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

    /**
     * Determine whether the user can issue an invoice for the job.
     */
    public function issue(User $user, Job $job): bool
    {
        return $user->isFrontOffice() && $job->canBeInvoiced();
    }

    /**
     * Determine whether the user can set the vehicle's next service from the job.
     */
    public function scheduleNextService(User $user, Job $job): bool
    {
        return $user->isFrontOffice() && $job->status === JobStatus::Completed;
    }

    /**
     * Determine whether the user can create or revise an estimate for the job.
     */
    public function estimate(User $user, Job $job): bool
    {
        return $user->isFrontOffice() && $job->canBeEstimated();
    }
}
