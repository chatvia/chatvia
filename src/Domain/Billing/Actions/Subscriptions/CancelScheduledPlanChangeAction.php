<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Subscriptions\StripeScheduleData;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

/**
 * Withdraws a plan change scheduled by {@see SchedulePlanChangeAction}:
 * releases the Stripe subscription schedule (the subscription keeps its current
 * price) and clears the pending-change metadata. Returns false when nothing was
 * pending.
 */
class CancelScheduledPlanChangeAction
{
    public function execute(User $user): bool
    {
        $billing = $user->accountBilling();
        $subscription = $billing?->subscribed() ? $billing->ownedSubscription('default') : null;

        if (! $subscription instanceof Subscription) {
            return false;
        }

        $current = $subscription->asStripeSubscription()->metadata->toArray();
        $metadata = StripeMetadata::from($current);

        if (! $metadata->hasPendingChange()) {
            return false;
        }

        Cashier::stripe()->subscriptionSchedules->retrieve((string) $metadata->scheduleId())->release();

        $subscription->updateStripeSubscription([
            'metadata' => array_merge($current, StripeScheduleData::withdrawn()->toArray()),
        ]);

        return true;
    }
}
