<?php

namespace App\Http\Controllers\Billing\Payments;

use Illuminate\Http\Request;

class BillingPaymentMethodDeleteController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = auth()->user();

        try {
            $paymentMethod = $user->findPaymentMethod($request->payment_method_id);

            if ($paymentMethod) {
                $paymentMethod->delete();

                return redirect()->route('billing.payment-methods')->with('success', 'Payment method removed successfully');
            }

            return back()->withErrors(['payment_method' => 'Payment method not found']);

        } catch (\Exception $exception) {
            return back()->withErrors(['payment_method' => 'Failed to remove payment method']);
        }
    }
}
