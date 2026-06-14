<?php

namespace App\Http\Controllers\Billing\Trials;

use App\Http\Requests\Billing\StartTrialCheckoutRequest;
use Domain\Billing\Actions\Trials\StartTrialCheckoutAction;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Throwable;

class TrialCheckoutController
{
    public function __invoke(StartTrialCheckoutRequest $request, StartTrialCheckoutAction $startTrial): RedirectResponse
    {
        try {
            $checkoutSession = $startTrial->execute($request->toData());

            return redirect($checkoutSession->url);
        } catch (InvalidArgumentException) {
            return to_route('home')->with('error', 'Invalid package selected.');
        } catch (Throwable $e) {
            report($e);

            return to_route('home')->with('error', 'Unable to create checkout session. Please try again.');
        }
    }
}
