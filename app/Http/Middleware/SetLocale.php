<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales supported by the application, in the order they're preferred
     * when negotiating against the Accept-Language header.
     */
    public const SUPPORTED_LOCALES = ['en', 'pt_BR', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        // Keeps Carbon's human-readable output (e.g. diffForHumans(), used
        // for the Diagram list's "last modified" column) in the same
        // language as the rest of the UI.
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $sessionLocale = $request->session()->get('locale');

        if (is_string($sessionLocale) && in_array($sessionLocale, self::SUPPORTED_LOCALES, true)) {
            return $sessionLocale;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);

        if ($preferred !== null) {
            return $preferred;
        }

        return config('app.fallback_locale');
    }
}
