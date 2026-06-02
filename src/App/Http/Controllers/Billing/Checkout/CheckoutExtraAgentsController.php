<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Requests\Billing\SubscribeExtraAgentsRequest;
use Domain\Billing\Actions\Subscriptions\SubscribeExtraAgentsAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;
use RuntimeException;
use Throwable;

class CheckoutExtraAgentsController
{
    public function __invoke(
        SubscribeExtraAgentsRequest $request,
        SubscribeExtraAgentsAction $action,
    ): Checkout|RedirectResponse {
        try {
            $checkout = $action->execute($request->toData());
        } catch (RuntimeException $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Extra agents checkout failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['addon' => 'Could not start Stripe checkout. Please try again.']);
        }

        // Quantity update on an existing active subscription — no checkout needed.
        if (! $checkout) {
            return back()->with('success', 'Extra agents quantity updated.');
        }

        return $checkout;
    }
}
