<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;

class StripePortalController
{
    public function __invoke(Request $request)
    {
        return $request->user()->redirectToBillingPortal(route('dashboard'));
    }
}
