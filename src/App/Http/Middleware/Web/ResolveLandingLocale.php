<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the public site's locale and the dashboard's locale in step, and handles
 * first-visit detection. Runs first in the landing route group, before the
 * laravel-localization middleware.
 *
 * Signed-in users: `users.locale` is the single source of truth shared with the
 * dashboard (HandleInertiaRequests shares it, the SPA resolves vue-i18n from it).
 *   - On a locale-prefixed URL (e.g. /da — i.e. the visitor picked a language in
 *     the landing switcher) we persist that locale to the account, so the
 *     dashboard follows.
 *   - On an un-prefixed URL (/) we seed the laravel-localization session from the
 *     saved preference, so the redirect sends them to their language — keeping the
 *     landing in step with a choice made in the dashboard.
 *
 * Guests: no browser-based auto-redirect — everyone stays on the English default
 * until they pick German or French from the switcher.
 */
class ResolveLandingLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = LaravelLocalization::getSupportedLanguagesKeys();
        $urlLocale = $request->segment(1);
        $hasUrlLocale = in_array($urlLocale, $supported, true);

        $user = $request->user();

        if ($user !== null) {
            if ($hasUrlLocale) {
                // The visitor explicitly chose this locale on the landing → make it
                // their account-wide preference so every surface follows.
                if ($user->locale !== $urlLocale) {
                    $user->locale = $urlLocale;
                    $user->save();
                }
                session(['locale' => $urlLocale]);
            } elseif (! empty($user->locale) && in_array($user->locale, $supported, true)) {
                // No locale in the URL: follow the account preference (set anywhere,
                // including the dashboard) so localeSessionRedirect routes them to it —
                // but only when it is still a supported landing locale. An account on a
                // now-disabled locale (e.g. a dashboard set to Arabic) falls through to
                // the English default rather than seeding a session mcamara can't route.
                session(['locale' => $user->locale]);
            }

            return $next($request);
        }

        // Guests stay on the English default and pick German or French from the
        // switcher — no browser-based auto-redirect.
        return $next($request);
    }
}
