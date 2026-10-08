<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Controllers\Billing\Checkout\Concerns\RunsAddonCheckout;
use App\Http\Requests\Billing\SubscribeMembersRequest;
use Domain\Billing\Actions\Subscriptions\SubscribeMembersAction;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe checkout for the Members add-on, or changes its quantity.
 */
class CheckoutMembersController
{
    use RunsAddonCheckout;

    public function __invoke(SubscribeMembersRequest $request, SubscribeMembersAction $action): Checkout|RedirectResponse
    {
        // No checkout when the quantity was updated on an active subscription.
        return $this->runAddonCheckout(
            fn (): ?Checkout => $action->execute($request->toData()),
            'Members quantity updated.',
        );
    }
}
