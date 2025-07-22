<?php

namespace App\Http\Controllers\Billing\Payments;

use Illuminate\Http\Request;

class BillingPaymentMethodDefaultController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = auth()->user();

        try {
            $user->updateDefaultPaymentMethod($request->payment_method_id);

            return redirect()->route('billing.payment-methods')->with('success', 'Default payment method updated');

        } catch (\Exception $exception) {
            return back()->withErrors(['payment_method' => 'Failed to update default payment method']);
        }
    }
}
