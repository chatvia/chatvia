<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the self-serve sign-up form: open while
 * `chatvia.registration_enabled` is on (the default; admin panel, Settings →
 * Workspaces). A closed form answers 404.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) (config('chatvia.registration_enabled') ?? true), 404);

        return $next($request);
    }
}
