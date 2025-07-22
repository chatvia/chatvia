<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use Illuminate\Http\Request;

class BillingSubscriptionResumeController
{
    public function __invoke(Request $request)
    {
        $request->user()->subscription()?->resume();
    }
}
