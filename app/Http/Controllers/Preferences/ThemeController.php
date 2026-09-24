<?php

namespace App\Http\Controllers\Preferences;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Preferences\UpdateThemeRequest;
use App\Services\PreferenceService;
use Illuminate\Http\RedirectResponse;

class ThemeController extends Controller
{
    public function __construct(private PreferenceService $preferences) {}

    public function __invoke(UpdateThemeRequest $request): RedirectResponse
    {
        $this->preferences->setTheme($request->user(), $request->enum('theme', Theme::class));

        return back(fallback: route('dashboard'));
    }
}
