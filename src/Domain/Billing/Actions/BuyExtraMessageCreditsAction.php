<?php

namespace Domain\Billing\Actions;

use App\Auth\Models\User;
use Laravel\Cashier\Checkout;

class BuyExtraMessageCreditsAction
{
    public function execute(User $user, int $quantity): Checkout
    {
        $items = [
            config('billing.addons.extra_message_credits.stripe_price_id') => $quantity,
        ];

        $session = [
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.index'),
            'metadata' => [
                'id' => 'extra_message_credits',
                'type' => 'addon',
                'quantity' => $quantity,
                'messages' => 1000 * $quantity,
            ],
        ];

        return $user->accountBilling()->checkout($items, $session);
    }
}
