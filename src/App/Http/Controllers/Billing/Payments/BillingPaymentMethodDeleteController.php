<?php

namespace App\Http\Controllers\Billing\Payments;

use App\Http\Requests\Billing\PaymentMethodIdRequest;
use Domain\Billing\Actions\Account\DeletePaymentMethodAction;
use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * Removes one of the workspace's saved cards.
 */
class BillingPaymentMethodDeleteController
{
    public function __invoke(PaymentMethodIdRequest $request, DeletePaymentMethodAction $delete): RedirectResponse
    {
        try {
            $deleted = $delete->execute($request->toData());
        } catch (Exception) {
            return back()->withErrors(['payment_method' => __('Failed to remove payment method')]);
        }

        if (! $deleted) {
            return back()->withErrors(['payment_method' => __('Payment method not found')]);
        }

        return to_route('billing.payment-methods')->with('success', __('Payment method removed successfully'));
    }
}
