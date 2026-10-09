<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Http\Requests\Billing\ChangePlanRequest;
use Domain\Billing\Actions\Subscriptions\SchedulePlanChangeAction;
use Illuminate\Http\RedirectResponse;

/**
 * Schedules a move to another plan at the end of the billing period.
 */
class BillingSubscriptionChangeController
{
    public function __invoke(ChangePlanRequest $request, SchedulePlanChangeAction $schedule): RedirectResponse
    {
        if (! $schedule->execute($request->toData())) {
            return to_route('billing.index')->with('error', __('There is an issue with your subscription. Please try again later.'));
        }

        return to_route('billing.index')->with('success', __('Your subscription plan has been updated successfully!'));
    }
}
