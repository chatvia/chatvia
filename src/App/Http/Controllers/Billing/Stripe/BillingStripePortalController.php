<?php

namespace App\Http\Controllers\Billing\Stripe;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends the owner to the Stripe customer portal.
 */
class BillingStripePortalController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Cashier's `Billable` trait lives on `UserBilling`, not `User`.
        return $request->user()->accountBillingOrFail()->redirectToBillingPortal(route('dashboard'));
    }
}
