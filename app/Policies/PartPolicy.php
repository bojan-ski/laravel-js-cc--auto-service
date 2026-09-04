<?php

namespace App\Policies;

use App\Models\Part;
use App\Models\User;

class PartPolicy
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
    public function view(User $user, Part $part): bool
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
    public function update(User $user, Part $part): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Part $part): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Part $part): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can see soft-deleted parts at all.
     */
    public function viewDeleted(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Importing both creates parts and moves stock, so it is the conjunction of
     * create and adjustStock — which carry this same rule.
     */
    public function import(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can record a stock movement by hand.
     */
    public function adjustStock(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can see the selling price.
     */
    public function viewPricing(User $user): bool
    {
        return $user->isFrontOffice();
    }

    /**
     * Determine whether the user can see what the shop paid.
     */
    public function viewCostPrice(User $user): bool
    {
        return $user->isAdmin();
    }
}
