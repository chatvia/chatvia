<?php

namespace Domain\Billing\Actions;

use App\Auth\Models\User;

class DeletePaymentMethodAction
{
    public function execute(User $user, string $paymentMethodId): bool
    {
        $paymentMethod = $user->findPaymentMethod($paymentMethodId);

        if (! $paymentMethod) {
            return false;
        }

        $paymentMethod->delete();

        return true;
    }
}
