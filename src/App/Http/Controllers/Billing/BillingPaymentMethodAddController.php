<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingPaymentMethodAddController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('billing/AddPaymentMethod', [
            'intent' => $request->user()->createSetupIntent(),
        ]);
    }
}
