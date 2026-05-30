<?php

namespace App\Http\Controllers\Billing\Home;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController
{
    public function __invoke(Request $request)
    {
        if (! $request->user()->accountBilling()?->subscribed()) {
            return redirect()->route('billing.packages');
        }

        return Inertia::render('billing/Index');
    }
}
