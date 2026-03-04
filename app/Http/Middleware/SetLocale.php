<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredLocale = Locale::tryFrom((string) config('app.locale'));
        $sessionLocale = Locale::tryFrom((string) $request->session()->get('locale'));
        $activeLocale = $sessionLocale ?? $configuredLocale ?? Locale::Croatian;

        app()->setLocale($activeLocale->value);

        return $next($request);
    }
}
