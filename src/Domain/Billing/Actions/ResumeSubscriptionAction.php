<?php

namespace Domain\Billing\Actions;

use App\Auth\Models\User;
use Laravel\Cashier\Cashier;

class ResumeSubscriptionAction
{
    public function execute(User $user): bool
    {
        $subscription = $user->subscription();

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
