<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Account;

use Domain\Billing\Data\Account\PaymentMethodData;
use Laravel\Cashier\PaymentMethod;

/**
 * Removes a saved payment method from the workspace's Stripe customer.
 * Returns false when the workspace has no such payment method.
 */
class DeletePaymentMethodAction
{
    public function execute(PaymentMethodData $data): bool
    {
        // Cashier's `Billable` trait lives on `UserBilling`, not `User`.
        $paymentMethod = $data->user->accountBillingOrFail()->findPaymentMethod($data->paymentMethodId);

        if (! $paymentMethod instanceof PaymentMethod) {
            return false;
        }

        $paymentMethod->delete();

        return true;
    }
}
