<?php

namespace App\Policies;

use App\Models\User;

class ShopSettingPolicy
{
    /**
     * Determine whether the user can change the shop's settings.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
