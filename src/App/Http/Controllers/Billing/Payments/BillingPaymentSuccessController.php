<?php

namespace App\Http\Controllers\Billing\Payments;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingPaymentSuccessController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('billing/PaymentSuccess');
    }
}
