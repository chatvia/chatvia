<?php

namespace App\Http\Controllers\Billing\Home;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The billing page. Accounts without a subscription are sent to the plan
 * picker.
 */
class BillingController
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if (! $request->user()->accountBilling()?->subscribed()) {
            return to_route('billing.packages');
        }

        return Inertia::render('billing/Index');
    }
}
