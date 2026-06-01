<?php

namespace Domain\Billing\Actions\Account;

use Domain\Billing\Data\BuyExtraMessageCreditsData;
use Laravel\Cashier\Checkout;

class BuyExtraMessageCreditsAction
{
    public function execute(BuyExtraMessageCreditsData $data): Checkout
    {
        $items = [
            config('billing.addons.extra_message_credits.stripe_price_id') => $data->quantity,
        ];

        $session = [
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.index'),
            'metadata' => [
                'id' => 'extra_message_credits',
                'type' => 'addon',
                'quantity' => $data->quantity,
                'messages' => 1000 * $data->quantity,
            ],
        ];

        return $data->user->accountBilling()->checkout($items, $session);
    }
}
