<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;

class CancelSubscriptionController
{
    public function __invoke(Request $request)
    {
        $request->user()->cancelSubscription();
    }
}
