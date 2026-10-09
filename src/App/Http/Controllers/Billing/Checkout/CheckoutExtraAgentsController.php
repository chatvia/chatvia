<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Controllers\Billing\Checkout\Concerns\RunsAddonCheckout;
use App\Http\Requests\Billing\SubscribeExtraAgentsRequest;
use Domain\Billing\Actions\Addons\SubscribeAddonAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe checkout for the Extra Agents add-on, or changes its
 * quantity. No checkout is needed when the quantity of an active subscription
 * was updated.
 */
class CheckoutExtraAgentsController
{
    use RunsAddonCheckout;

    public function __invoke(SubscribeExtraAgentsRequest $request, SubscribeAddonAction $subscribe): Checkout|RedirectResponse
    {
        return $this->runAddonCheckout(
            fn (): ?Checkout => $subscribe->execute($request->toData()),
            __('Extra agents quantity updated.'),
        );
    }
}
