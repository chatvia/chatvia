<?php

namespace Domain\Billing\Actions\Account;

use App\Identity\Workspace\Models\User;

class SetDefaultPaymentMethodAction
{
    public function execute(User $user, string $paymentMethodId): void
    {
        $billing = $user->accountBilling();

        // Skip the Stripe call when the account has no billing row or no
        // Stripe customer yet — Cashier throws InvalidCustomer otherwise,
        // which surfaces as a 500 on the (legitimate) "I'm a chatter that
        // can't pay anyway" / "I haven't set up billing yet" paths.
        if ($billing === null || ! $billing->hasStripeId()) {
            return;
        }

        $billing->updateDefaultPaymentMethod($paymentMethodId);
    }
}
