<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\Auth\UpdateGuestLocaleRequest;
use Illuminate\Http\RedirectResponse;

class GuestLocaleController extends Controller
{
    public function __invoke(UpdateGuestLocaleRequest $request): RedirectResponse
    {
        $request->session()->put(SetLocale::SESSION_KEY, $request->enum('locale', Locale::class)->value);

        return back(fallback: route('login'));
    }
}
