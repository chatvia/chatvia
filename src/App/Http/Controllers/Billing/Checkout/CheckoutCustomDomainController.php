<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Controllers\Billing\Checkout\Concerns\RunsAddonCheckout;
use App\Http\Requests\Billing\SubscribeCustomDomainRequest;
use Domain\Billing\Actions\Subscriptions\SubscribeCustomDomainAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe checkout for the Custom Domain add-on, or changes its quantity.
 */
class CheckoutCustomDomainController
{
    use RunsAddonCheckout;

    public function __invoke(SubscribeCustomDomainRequest $request, SubscribeCustomDomainAction $action): Checkout|RedirectResponse
    {
        // No checkout when the quantity was updated on an active subscription.
        return $this->runAddonCheckout(
            fn (): ?Checkout => $action->execute($request->toData()),
            'Custom domain quantity updated.',
        );
    }
}
