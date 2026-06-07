<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\SubscribeRemoveBrandingAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;
use RuntimeException;
use Throwable;

class CheckoutRemoveBrandingController
{
    public function __invoke(Request $request, SubscribeRemoveBrandingAction $action): Checkout|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $checkout = $action->execute($user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Remove branding checkout failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['addon' => 'Could not start Stripe checkout. Please try again.']);
        }

        // Already subscribed — no checkout needed.
        if (! $checkout) {
            return back()->with('success', 'Branding is already removed.');
        }

        return $checkout;
    }
}
