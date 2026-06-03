<?php

namespace App\Http\Controllers\Billing\Payments;

use App\Http\Requests\Billing\PaymentMethodIdRequest;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\DeletePaymentMethodAction;
use Exception;
use Illuminate\Http\RedirectResponse;

class BillingPaymentMethodDeleteController
{
    public function __invoke(
        PaymentMethodIdRequest $request,
        DeletePaymentMethodAction $delete,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        try {
            $deleted = $delete->execute($user, $request->string('payment_method_id')->toString());
        } catch (Exception) {
            return back()->withErrors(['payment_method' => 'Failed to remove payment method']);
        }

        if (! $deleted) {
            return back()->withErrors(['payment_method' => 'Payment method not found']);
        }

        return redirect()->route('billing.payment-methods')->with('success', 'Payment method removed successfully');
    }
}
