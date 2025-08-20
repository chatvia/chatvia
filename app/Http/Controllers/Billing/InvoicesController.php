<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoicesController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('billing/Invoices', [
            'invoices' => $request->user()->invoices()->toArray(),
        ]);
    }
}
