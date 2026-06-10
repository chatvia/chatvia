<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Http\Requests\Billing\SubscribeMembersRequest;
use Domain\Billing\Actions\Subscriptions\SubscribeMembersAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;
use RuntimeException;
use Throwable;

class CheckoutMembersController
{
    public function __invoke(
        SubscribeMembersRequest $request,
        SubscribeMembersAction $action,
    ): Checkout|RedirectResponse {
        try {
            $checkout = $action->execute($request->toData());
        } catch (RuntimeException $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Members checkout failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['addon' => 'Could not start Stripe checkout. Please try again.']);
        }

        // Quantity update on an existing active subscription — no checkout needed.
        if (! $checkout) {
            return back()->with('success', 'Members quantity updated.');
        }

        return $checkout;
    }
}
