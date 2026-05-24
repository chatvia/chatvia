<?php

namespace App\Http\Controllers\Billing\Stripe;

use Illuminate\Http\Request;

class BillingStripePortalController
{
    public function __invoke(Request $request)
    {
        return $request->user()->redirectToBillingPortal(route('dashboard'));
    }
}
