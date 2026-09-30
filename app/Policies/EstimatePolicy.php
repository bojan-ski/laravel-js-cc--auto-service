<?php

namespace App\Policies;

use App\Models\Estimate;
use App\Models\User;

class EstimatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Estimate $estimate): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can record the customer's decision on the model.
     */
    public function decide(User $user, Estimate $estimate): bool
    {
        return $user->isFrontOffice() && $estimate->canBeDecided();
    }

    /**
     * Determine whether the user can email the model to the customer.
     */
    public function email(User $user, Estimate $estimate): bool
    {
        return $user->isFrontOffice() && $estimate->canBeEmailed();
    }
}
