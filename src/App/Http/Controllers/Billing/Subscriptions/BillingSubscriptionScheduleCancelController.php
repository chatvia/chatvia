<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Billing\Data\StripeScheduleData;
use Illuminate\Http\Request;
use Stripe\SubscriptionSchedule;

class BillingSubscriptionScheduleCancelController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (! $user->subscribed()) {
            return to_route('billing.index')->with('error', 'You are not currently subscribed to any plan.');
        }

        $subscription = $user->subscription();

        if (! $subscription) {
            return to_route('billing.index')->with('error', 'No active subscription found.');
        }

        $stripeSubscription = $subscription->asStripeSubscription();
        $metadata = $stripeSubscription->metadata->toArray();

        if (! isset($metadata['pending_change']) || $metadata['pending_change'] !== 'true') {
            return to_route('billing.index')->with('error', 'No pending plan change found to cancel.');
        }

        $schedule = SubscriptionSchedule::retrieve($metadata['schedule_id']);
        $schedule->release();

        $subscription->updateStripeSubscription([
            'metadata' => array_merge($metadata, StripeScheduleData::from([
                'schedule_id' => null,
                'pending_change' => 'false',
                'from_package' => null,
                'to_package' => null,
                'new_price_id' => null,
                'is_yearly' => null,
                'effective_date' => null,
                'canceled_at' => now()->toISOString(),
                'requested_at' => '',
            ])->toArray()),
        ]);

        return to_route('billing.index')->with('success', 'Your scheduled plan change has been cancelled successfully.');
    }
}
