<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Http\Requests\Billing\SubscribeRequest;
use Domain\Billing\Actions\SubscribeUserAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

class BillingSubscribeController
{
    public function __invoke(SubscribeRequest $request, SubscribeUserAction $subscribe): Checkout|RedirectResponse
    {
        $checkout = $subscribe->execute($request->toData());

        if (! $checkout) {
            return to_route('dashboard');
        }

        return $checkout;
    }
}
