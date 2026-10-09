<?php

namespace App\Http\Middleware\Web;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the signed-in user's saved UI language for the request, so the root
 * view renders with the right `lang`/`dir` on first paint. Only configured
 * languages are used; anything else keeps the application default. On the
 * public site a language segment in the URL (`/ar/...`) wins.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // On the public site an explicit locale segment in the URL (e.g. `/ar/...`)
        // is authoritative — laravel-localization has already set the app locale from
        // it. Don't let a signed-in user's saved preference override the language they
        // deliberately navigated to, or the page would render in the wrong language.
        if (in_array($request->segment(1), LaravelLocalization::getSupportedLanguagesKeys(), true)) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user();

        $locale = $user?->locale;

        if (is_string($locale) && array_key_exists($locale, config('chatvia.languages'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
