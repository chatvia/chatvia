<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Http\Requests\Billing\ChangePlanRequest;
use Domain\Billing\Actions\SchedulePlanChangeAction;
use Illuminate\Http\RedirectResponse;

class BillingSubscriptionChangeController
{
    public function __invoke(ChangePlanRequest $request, SchedulePlanChangeAction $schedule): RedirectResponse
    {
        if (! $schedule->execute($request->toData())) {
            return to_route('billing.index')->with('error', 'There is an issue with your subscription. Please try again later.');
        }

        return to_route('billing.index')->with('success', 'Your subscription plan has been updated successfully!');
    }
}
