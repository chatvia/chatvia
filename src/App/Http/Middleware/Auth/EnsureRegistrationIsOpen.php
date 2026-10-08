<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the self-serve sign-up form. It is closed unless
 * `billing.registration_enabled` is on — by default only when billing is
 * turned off, since the hosted product creates accounts through the Stripe
 * trial checkout. A closed form answers 404.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('billing.registration_enabled', false), 404);

        return $next($request);
    }
}
