<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('billing/Index');
    }
}
