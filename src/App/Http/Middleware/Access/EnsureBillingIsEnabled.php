<?php

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the billing pages and the Stripe checkout flows (plans, checkout,
 * subscriptions, invoices, payment methods, add-ons, trial sign-up). When
 * billing is turned off (`billing.enabled`, a self-hosted install) these
 * routes answer 404, as if they did not exist.
 */
class EnsureBillingIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('billing.enabled', true), 404);

        return $next($request);
    }
}
