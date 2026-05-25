<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use Illuminate\Http\Request;
use Stripe\SubscriptionSchedule;

class BillingSubscriptionCancelController
{
    public function __invoke(Request $request): void
    {
        $user = $request->user();
        $subscription = $user->subscription();

        if ($subscription) {
            // Clear any pending plan changes before cancelling
            $currentMetadata = $subscription->asStripeSubscription()->metadata->toArray();

            if (isset($currentMetadata['schedule_id'])) {
                $schedule = SubscriptionSchedule::retrieve($currentMetadata['schedule_id']);
                $schedule->release();
            }

            if (isset($currentMetadata['pending_change']) && $currentMetadata['pending_change'] === 'true') {
                $subscription->updateStripeSubscription([
                    'metadata' => array_merge($currentMetadata, [
                        'pending_change' => 'false',
                        'from_package' => null,
                        'to_package' => null,
                        'new_price_id' => null,
                        'scheduled_at' => null,
                        'cancelled_at' => now()->toISOString(),
                        'cancellation_reason' => 'subscription_cancelled',
                    ]),
                ]);
            }
        }

        $user->cancelSubscription();
    }
}
