<?php

namespace Domain\Billing\Actions\Account;

use App\Workspace\Models\User;

class DeletePaymentMethodAction
{
    public function execute(User $user, string $paymentMethodId): bool
    {
        // Cashier's `Billable` trait lives on `UserBilling`, not `User`.
        $paymentMethod = $user->accountBillingOrFail()->findPaymentMethod($paymentMethodId);

        if (! $paymentMethod) {
            return false;
        }

        $paymentMethod->delete();

        return true;
    }
}
