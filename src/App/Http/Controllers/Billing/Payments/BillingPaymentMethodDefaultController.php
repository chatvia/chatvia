<?php

namespace App\Http\Controllers\Billing\Payments;

use App\Http\Requests\Billing\PaymentMethodIdRequest;
use Domain\Billing\Actions\Account\SetDefaultPaymentMethodAction;
use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * Makes one of the saved cards the workspace's default payment method.
 */
class BillingPaymentMethodDefaultController
{
    public function __invoke(PaymentMethodIdRequest $request, SetDefaultPaymentMethodAction $setDefault): RedirectResponse
    {
        try {
            $setDefault->execute($request->toData());
        } catch (Exception) {
            return back()->withErrors(['payment_method' => __('Failed to update default payment method')]);
        }

        return to_route('billing.payment-methods')->with('success', __('Default payment method updated'));
    }
}
