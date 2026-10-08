<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Controllers\Billing\Checkout\Concerns\RunsAddonCheckout;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\SubscribeRemoveBrandingAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Cashier\Checkout;

/**
 * Starts a Stripe checkout for the Remove Branding add-on.
 */
class CheckoutRemoveBrandingController
{
    use RunsAddonCheckout;

    public function __invoke(Request $request, SubscribeRemoveBrandingAction $action): Checkout|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // No checkout when the add-on is already active.
        return $this->runAddonCheckout(
            fn (): ?Checkout => $action->execute($user),
            'Branding is already removed.',
        );
    }
}
