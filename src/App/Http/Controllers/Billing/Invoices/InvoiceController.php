<?php

namespace App\Http\Controllers\Billing\Invoices;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists the workspace's Stripe invoices.
 */
class InvoiceController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('billing/Invoices', [
            'invoices' => $request->user()->accountBilling()?->invoices()->toArray() ?? [],
        ]);
    }
}
