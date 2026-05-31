<?php

namespace App\Http\Controllers\Billing\Stripe;

use Illuminate\Http\Request;

class BillingStripePortalController
{
    public function __invoke(Request $request)
    {
        // Cashier's `Billable` trait lives on `UserBilling`, not `User`.
        return $request->user()->accountBillingOrFail()->redirectToBillingPortal(route('dashboard'));
    }
}
