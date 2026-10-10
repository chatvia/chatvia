<?php

namespace App\Http\Middleware\Web;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the signed-in user's saved UI language for the request, so the root
 * view renders with the right `lang`/`dir` on first paint. Only configured
 * languages are used; anything else keeps the application default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $locale = $user?->locale;

        if (is_string($locale) && array_key_exists($locale, config('chatvia.languages'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
