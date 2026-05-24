<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;

class BillingSubscriptionResumeController
{
    public function __invoke(Request $request)
    {
        $subscription = $request->user()->subscription();

        if (! $subscription) {
            return back()->with('error', 'No subscription found.');
        }

        $stripeSubscription = $subscription->asStripeSubscription();

        $scheduleId = $stripeSubscription->schedule;

        if ($scheduleId) {
            Cashier::stripe()->subscriptionSchedules->release($scheduleId);
        }

        $subscription->resume();

    }
}
