<?php

namespace App\Http\Controllers\Preferences;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Preferences\UpdateLocaleRequest;
use App\Services\PreferenceService;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __construct(private PreferenceService $preferences) {}

    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $this->preferences->setLocale($request->user(), $request->enum('locale', Locale::class));

        return back(fallback: route('dashboard'));
    }
}
