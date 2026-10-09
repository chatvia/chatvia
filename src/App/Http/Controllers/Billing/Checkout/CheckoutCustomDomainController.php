<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Controllers\Billing\Checkout\Concerns\RunsAddonCheckout;
use App\Http\Requests\Billing\SubscribeCustomDomainRequest;
use Domain\Billing\Actions\Addons\SubscribeAddonAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe checkout for the Custom Domain add-on, or changes its
 * quantity. No checkout is needed when the quantity of an active subscription
 * was updated.
 */
class CheckoutCustomDomainController
{
    use RunsAddonCheckout;

    public function __invoke(SubscribeCustomDomainRequest $request, SubscribeAddonAction $subscribe): Checkout|RedirectResponse
    {
        return $this->runAddonCheckout(
            fn (): ?Checkout => $subscribe->execute($request->toData()),
            __('Custom domain quantity updated.'),
        );
    }
}
