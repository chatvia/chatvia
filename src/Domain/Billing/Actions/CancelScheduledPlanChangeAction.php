<?php

namespace Domain\Billing\Actions;

use App\Auth\Models\User;
use App\Billing\Data\StripeScheduleData;
use Stripe\SubscriptionSchedule;

class CancelScheduledPlanChangeAction
{
    public function execute(User $user): bool
    {
        $billing = $user->accountBilling();

        if (! $billing?->subscribed()) {
            return false;
        }

        $subscription = $billing->subscription();

        if (! $subscription) {
            return false;
        }

        $stripeSubscription = $subscription->asStripeSubscription();
        $metadata = $stripeSubscription->metadata->toArray();

        if (! isset($metadata['pending_change']) || $metadata['pending_change'] !== 'true') {
            return false;
        }

        SubscriptionSchedule::retrieve($metadata['schedule_id'])->release();

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

        return true;
    }
}
