<?php

namespace App\Http\Controllers\Billing\Trials;

use App\Http\Requests\Billing\StartTrialCheckoutRequest;
use Domain\Billing\Actions\Trials\StartTrialCheckoutAction;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Throwable;

/**
 * The public "start a trial" button: opens a Stripe Checkout for the chosen
 * plan. Stripe or configuration errors are reported and shown as a generic
 * message.
 */
class TrialCheckoutController
{
    public function __invoke(StartTrialCheckoutRequest $request, StartTrialCheckoutAction $startTrial): RedirectResponse
    {
        try {
            return redirect()->away($startTrial->execute($request->toData())->url);
        } catch (InvalidArgumentException) {
            return to_route('home')->with('error', __('Invalid package selected.'));
        } catch (Throwable $e) {
            report($e);

            return to_route('home')->with('error', __('Unable to create checkout session. Please try again.'));
        }
    }
}
