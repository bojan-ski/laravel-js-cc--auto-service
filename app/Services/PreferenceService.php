<?php

namespace App\Services;

use App\Enums\Theme;
use App\Models\User;

class PreferenceService
{
    public function setTheme(User $user, Theme $theme): void
    {
        $user->theme = $theme;
        $user->save();
    }
}
