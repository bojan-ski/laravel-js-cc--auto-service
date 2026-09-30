<?php

namespace App\Policies;

use App\Models\JobAttachment;
use App\Models\User;

class JobAttachmentPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JobAttachment $attachment): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, JobAttachment $attachment): bool
    {
        if (! $user->isFrontOffice() && ! $attachment->isUploadedBy($user)) {
            return false;
        }

        return ! $attachment->isCheckInPhoto() || $attachment->job->acceptsCheckIn();
    }
}
