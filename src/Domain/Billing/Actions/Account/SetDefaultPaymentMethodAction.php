<?php

namespace Domain\Billing\Actions\Account;

use Domain\Billing\Data\Account\PaymentMethodData;

/**
 * Makes a saved payment method the default the workspace is charged with.
 */
class SetDefaultPaymentMethodAction
{
    public function execute(PaymentMethodData $data): void
    {
        $billing = $data->user->accountBilling();

        // Skip the Stripe call when the account has no billing row or no
        // Stripe customer yet — Cashier throws InvalidCustomer otherwise,
        // which surfaces as a 500 on the (legitimate) "I'm a chatter that
        // can't pay anyway" / "I haven't set up billing yet" paths.
        if ($billing === null || ! $billing->hasStripeId()) {
            return;
        }

        $billing->updateDefaultPaymentMethod($data->paymentMethodId);
    }
}
