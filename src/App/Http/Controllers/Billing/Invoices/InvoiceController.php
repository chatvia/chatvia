<?php

namespace App\Http\Controllers\Billing\Invoices;

use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('billing/Invoices', [
            'invoices' => $request->user()->accountBilling()?->invoices()->toArray() ?? [],
        ]);
    }
}
