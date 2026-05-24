<?php

namespace App\Http\Controllers\Billing\Checkout;

use Illuminate\Http\Request;

class CheckoutExtraMessagesController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $items = [
            config('billing.addons.extra_message_credits.stripe_price_id') => $request->input('quantity'),
        ];

        $session = [
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.index'),
            'metadata' => [
                'id' => 'extra_message_credits',
                'type' => 'addon',
                'quantity' => $request->input('quantity'),
                'messages' => 1000 * $request->input('quantity'),
            ],
        ];

        return $request->user()->checkout($items, $session);
    }
}
