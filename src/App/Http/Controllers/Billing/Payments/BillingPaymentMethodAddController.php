<?php

namespace App\Http\Controllers\Billing\Payments;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingPaymentMethodAddController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('billing/AddPaymentMethod', [
            // Cashier's `Billable` trait lives on `UserBilling`, not `User`,
            // so the SetupIntent is created against the billing row.
            'intent' => $request->user()->accountBillingOrFail()->createSetupIntent(),
        ]);
    }
}
