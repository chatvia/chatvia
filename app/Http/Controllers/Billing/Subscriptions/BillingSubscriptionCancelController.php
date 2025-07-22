<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use Illuminate\Http\Request;

class BillingSubscriptionCancelController
{
    public function __invoke(Request $request)
    {
        $request->user()->cancelSubscription();
    }
}
