<?php

namespace App\Http\Controllers\Billing\Checkout\Concerns;

use Closure;
use Domain\Billing\Exceptions\AddonNotConfiguredException;
use Illuminate\Http\RedirectResponse;
use Laravel\Cashier\Checkout;
use Throwable;

/**
 * Shared error handling for the add-on checkout controllers. A missing add-on
 * price is shown to the user as is. Any other failure is reported and replaced
 * by a generic message, so database or Stripe error text never reaches the page.
 */
trait RunsAddonCheckout
{
    /**
     * @param  Closure(): ?Checkout  $start  returns null when no checkout is needed
     */
    private function runAddonCheckout(Closure $start, string $noCheckoutMessage): Checkout|RedirectResponse
    {
        try {
            $checkout = $start();
        } catch (AddonNotConfiguredException $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['addon' => __('Could not start Stripe checkout. Please try again.')]);
        }

        return $checkout ?? back()->with('success', $noCheckoutMessage);
    }
}
