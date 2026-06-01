<?php

namespace App\Http\Controllers\Billing\Payments;

use App\Auth\Models\User;
use App\Http\Requests\Billing\PaymentMethodIdRequest;
use Domain\Billing\Actions\Account\SetDefaultPaymentMethodAction;
use Exception;
use Illuminate\Http\RedirectResponse;

class BillingPaymentMethodDefaultController
{
    public function __invoke(
        PaymentMethodIdRequest $request,
        SetDefaultPaymentMethodAction $setDefault,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $setDefault->execute($user, $request->string('payment_method_id')->toString());
        } catch (Exception) {
            return back()->withErrors(['payment_method' => 'Failed to update default payment method']);
        }

        return redirect()->route('billing.payment-methods')->with('success', 'Default payment method updated');
    }
}
