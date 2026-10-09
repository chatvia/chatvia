<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Http\Requests\Billing\SubscribeRequest;
use Domain\Billing\Actions\Subscriptions\SubscribeUserAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe Checkout for a plan, or goes back to the dashboard when the
 * plan cannot be bought self-serve.
 */
class BillingSubscribeController
{
    public function __invoke(SubscribeRequest $request, SubscribeUserAction $subscribe): Checkout|RedirectResponse
    {
        $checkout = $subscribe->execute($request->toData());

        if (! $checkout instanceof Checkout) {
            return to_route('dashboard');
        }

        return $checkout;
    }
}
