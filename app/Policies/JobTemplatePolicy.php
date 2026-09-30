<?php

namespace App\Policies;

use App\Models\JobTemplate;
use App\Models\User;

class JobTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can see deleted templates in the list.
     */
    public function viewDeleted(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, JobTemplate $jobTemplate): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JobTemplate $jobTemplate): bool
    {
        return $user->isAdmin() && ! $jobTemplate->trashed();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, JobTemplate $jobTemplate): bool
    {
        return $user->isAdmin() && $jobTemplate->trashed();
    }
}
