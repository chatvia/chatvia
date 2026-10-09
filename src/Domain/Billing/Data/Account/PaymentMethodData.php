<?php

namespace Domain\Billing\Data\Account;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * One of the workspace's saved Stripe payment methods, picked by its id.
 */
class PaymentMethodData extends Data
{
    public function __construct(
        public User $user,
        public string $paymentMethodId,
    ) {}
}
