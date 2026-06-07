<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\Addon;

/**
 * Cancels the workspace's `remove_branding` add-on immediately. There is no
 * quota column to reset (boolean add-on); once the subscription is gone,
 * {@see User::canHideBranding()} reads false and the badge reappears.
 */
class CancelRemoveBrandingAction
{
    public function execute(User $user): void
    {
        $billing = $user->accountBillingOrFail();
        $subscription = $billing->ownedSubscription(Addon::RemoveBranding->value);

        if ($subscription && ! $subscription->canceled()) {
            $subscription->cancelNow();
        }
    }
}
