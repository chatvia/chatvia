<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Controllers\Billing\Checkout\Concerns\RunsAddonCheckout;
use App\Http\Requests\Billing\SubscribeExtraAgentsRequest;
use Domain\Billing\Actions\Subscriptions\SubscribeExtraAgentsAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe checkout for the Extra Agents add-on, or changes its quantity.
 */
class CheckoutExtraAgentsController
{
    use RunsAddonCheckout;

    public function __invoke(SubscribeExtraAgentsRequest $request, SubscribeExtraAgentsAction $action): Checkout|RedirectResponse
    {
        // No checkout when the quantity was updated on an active subscription.
        return $this->runAddonCheckout(
            fn (): ?Checkout => $action->execute($request->toData()),
            'Extra agents quantity updated.',
        );
    }
}
