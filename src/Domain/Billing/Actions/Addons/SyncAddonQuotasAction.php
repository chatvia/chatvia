<?php

namespace Domain\Billing\Actions\Addons;

use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserBilling;
use Laravel\Cashier\Subscription;

/**
 * Rebuilds a workspace's add-on quota columns (extra agents, members, custom
 * domains) from its local Cashier subscription rows.
 *
 * The Cashier `subscriptions` table is the source of truth: Cashier keeps it in
 * sync with Stripe, and every add-on is its own subscription whose `type` is the
 * {@see Addon} value. A valid subscription (active, trialing or in its grace
 * period) grants its quantity; anything else grants zero. Rebuilding from that
 * table instead of from webhook metadata makes the result the same no matter
 * which Stripe event triggered it, and lets the same Action repair past data.
 *
 * Subscription-only add-ons (e.g. remove-branding) have no quota column and are
 * skipped — their entitlement is read live from the subscription.
 */
class SyncAddonQuotasAction
{
    public function execute(UserBilling $billing): void
    {
        $owner = $billing->user;

        if ($owner === null) {
            return;
        }

        $quotas = [];

        foreach (Addon::cases() as $addon) {
            $column = $addon->quotaColumn();

            if ($column === null) {
                continue;
            }

            $quotas[$column] = $this->grantedQuantity($billing->ownedSubscription($addon->value));
        }

        $owner->quota()->updateOrCreate([], $quotas);
    }

    private function grantedQuantity(?Subscription $subscription): int
    {
        if (! $subscription instanceof Subscription || ! $subscription->valid()) {
            return 0;
        }

        // Add-ons are single-price subscriptions, so the quantity lives on the
        // subscription row; fall back to the first item for multi-item rows.
        return (int) ($subscription->quantity ?? $subscription->items()->value('quantity') ?? 0);
    }
}
