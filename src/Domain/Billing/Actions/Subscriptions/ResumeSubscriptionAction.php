<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Laravel\Cashier\Cashier;

/**
 * Resumes a cancelled plan subscription that is still in its grace period,
 * releasing any Stripe schedule first. Returns false when there is no
 * subscription.
 */
class ResumeSubscriptionAction
{
    public function execute(User $user): bool
    {
        $subscription = $user->accountBilling()?->ownedSubscription('default');

        if (! $subscription) {
            return false;
        }

        $stripeSubscription = $subscription->asStripeSubscription();

        if ($stripeSubscription->schedule) {
            Cashier::stripe()->subscriptionSchedules->release($stripeSubscription->schedule);
        }

        $subscription->resume();

        return true;
    }
}
