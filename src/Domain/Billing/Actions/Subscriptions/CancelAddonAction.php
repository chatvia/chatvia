<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\Addon;

/**
 * Cancels a workspace's addon subscription immediately and resets its quota
 * mirror. Replaces the per-addon Cancel*Action family (extra agents / members /
 * custom domains / remove-branding) — they were byte-identical bar the {@see Addon}
 * case and the quota reset.
 *
 * Quota handling is driven by {@see Addon::quotaColumn()}: addons with a mirror
 * column (extra agents, members, custom domains) get it zeroed; subscription-only
 * boolean addons like RemoveBranding have no column (returns null) and read their
 * entitlement live from the Cashier subscription (e.g. {@see User::canHideBranding()}),
 * so there is nothing to reset once the subscription is gone.
 */
class CancelAddonAction
{
    public function execute(User $user, Addon $addon): void
    {
        $billing = $user->accountBillingOrFail();
        $subscription = $billing->ownedSubscription($addon->value);

        if ($subscription && ! $subscription->canceled()) {
            $subscription->cancelNow();
        }

        $quotaColumn = $addon->quotaColumn();

        if ($quotaColumn !== null) {
            $user->quota()->updateOrCreate([], [$quotaColumn => 0]);
        }
    }
}
