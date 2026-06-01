<?php

namespace Domain\Billing\Actions\Account;

use App\Auth\Models\User;

class SetDefaultPaymentMethodAction
{
    public function execute(User $user, string $paymentMethodId): void
    {
        $user->accountBilling()?->updateDefaultPaymentMethod($paymentMethodId);
    }
}
