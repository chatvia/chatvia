<?php

namespace Domain\Billing\Actions;

use App\Auth\Models\User;

class SetDefaultPaymentMethodAction
{
    public function execute(User $user, string $paymentMethodId): void
    {
        $user->updateDefaultPaymentMethod($paymentMethodId);
    }
}
