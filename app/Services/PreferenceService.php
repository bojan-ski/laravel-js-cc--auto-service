<?php

namespace App\Services;

use App\Enums\Locale;
use App\Enums\Theme;
use App\Models\User;

class PreferenceService
{
    public function setTheme(User $user, Theme $theme): void
    {
        $user->theme = $theme;
        $user->save();
    }

    public function setLocale(User $user, Locale $locale): void
    {
        $user->locale = $locale;
        $user->save();
    }
}
