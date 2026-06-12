<?php

namespace App\Http\Middleware\Web;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the signed-in user's persisted UI locale for the request so the
     * root view renders with the correct `lang`/`dir` on first paint. Only the
     * configured languages are honoured; anything else (or no preference) keeps
     * the application default.
     */
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
