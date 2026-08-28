<?php

namespace App\Policies;

use App\Models\JobItem;
use App\Models\User;

class JobItemPolicy
{
    /**
     * Determine whether the user can edit the line item.
     */
    public function update(User $user, JobItem $item): bool
    {
        $item->loadMissing('job');

        return $item->job->isWorking()
            && ! $item->job->isLocked()
            && ($user->isFrontOffice() || $item->isOwnedBy($user));
    }

    /**
     * Determine whether the user can delete the line item.
     */
    public function delete(User $user, JobItem $item): bool
    {
        return $this->update($user, $item);
    }
}
