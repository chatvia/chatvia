<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Requests\Billing\SubscribeCustomDomainRequest;
use Domain\Billing\Actions\Subscriptions\SubscribeCustomDomainAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;
use RuntimeException;
use Throwable;

class CheckoutCustomDomainController
{
    public function __invoke(
        SubscribeCustomDomainRequest $request,
        SubscribeCustomDomainAction $action,
    ): Checkout|RedirectResponse {
        try {
            $checkout = $action->execute($request->toData());
        } catch (RuntimeException $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Custom domain checkout failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['addon' => 'Could not start Stripe checkout. Please try again.']);
        }

        // Quantity update on an existing active subscription — no checkout needed.
        if (! $checkout) {
            return back()->with('success', 'Custom domain quantity updated.');
        }

        return $checkout;
    }
}
