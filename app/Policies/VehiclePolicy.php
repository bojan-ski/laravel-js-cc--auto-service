<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vehicle $vehicle): bool
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
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reassign the model to another customer.
     */
    public function reassign(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can see soft-deleted vehicles at all.
     */
    public function viewDeleted(User $user): bool
    {
        return $user->isAdmin();
    }
}
