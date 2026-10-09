<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Subscriptions\StripeScheduleData;
use Laravel\Cashier\Cashier;

/**
 * Cancels the workspace's plan subscription at the end of the current period.
 * A pending plan change is withdrawn first: its Stripe schedule is released
 * (Stripe refuses to cancel a subscription a schedule still manages) and the
 * pending-change metadata is cleared.
 */
class CancelSubscriptionAction
{
    public function execute(User $user): void
    {
        $subscription = $user->accountBilling()?->ownedSubscription('default');

        if ($subscription === null) {
            return;
        }

        $current = $subscription->asStripeSubscription()->metadata->toArray();
        $metadata = StripeMetadata::from($current);

        if ($metadata->scheduleId() !== null) {
            Cashier::stripe()->subscriptionSchedules->retrieve($metadata->scheduleId())->release();
        }

        if ($metadata->hasPendingChange()) {
            $subscription->updateStripeSubscription([
                'metadata' => array_merge($current, StripeScheduleData::subscriptionCanceledMetadata()),
            ]);
        }

        $subscription->cancel();
    }
}
