<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SESSION_KEY = 'locale';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::resolve($request)->value);

        return $next($request);
    }

    public static function resolve(Request $request): Locale
    {
        return $request->user()?->locale
            ?? Locale::tryFrom((string) $request->session()->get(self::SESSION_KEY))
            ?? Locale::shop();
    }
}
