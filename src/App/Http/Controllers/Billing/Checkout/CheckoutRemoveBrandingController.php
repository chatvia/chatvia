<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Controllers\Billing\Checkout\Concerns\RunsAddonCheckout;
use App\Http\Requests\Billing\SubscribeRemoveBrandingRequest;
use Domain\Billing\Actions\Addons\SubscribeAddonAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe checkout for the Remove Branding add-on. No checkout is
 * needed when the add-on is already active.
 */
class CheckoutRemoveBrandingController
{
    use RunsAddonCheckout;

    public function __invoke(SubscribeRemoveBrandingRequest $request, SubscribeAddonAction $subscribe): Checkout|RedirectResponse
    {
        return $this->runAddonCheckout(
            fn (): ?Checkout => $subscribe->execute($request->toData()),
            __('Branding is already removed.'),
        );
    }
}
