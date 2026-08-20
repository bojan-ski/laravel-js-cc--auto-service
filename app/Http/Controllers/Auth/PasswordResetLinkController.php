<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $request->recordAttempt();

        Password::sendResetLink($request->only('email'));

        return back()->with('success', __('app.auth.reset_link_generic'));
    }
}
