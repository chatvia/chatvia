<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\SubscribeMembersData;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Exceptions\AddonNotConfiguredException;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;

/**
 * Creates a Cashier subscription of type `members`. The plan's base
 * `workspace_users_limit` is left untouched on `user_quotas`; we mirror the
 * subscription quantity onto `extra_members_limit` via the webhook.
 */
class SubscribeMembersAction
{
    public function execute(SubscribeMembersData $data): ?Checkout
    {
        $priceId = config('billing.addons.members.stripe_price_id');

        if (! $priceId) {
            throw new AddonNotConfiguredException(
                'Members addon is not configured. Set STRIPE_MEMBERS_PRICE_ID in your environment.'
            );
        }

        $billing = $data->user->accountBillingOrFail();

        // Existing subscription → adjust quantity in place; no checkout needed.
        $existing = $billing->ownedSubscription(Addon::Members->value);

        if ($existing instanceof Subscription && $existing->active()) {
            $existing->updateQuantity($data->quantity);

            $data->user->quota()->updateOrCreate([], [Addon::Members->quotaColumn() => $data->quantity]);

            return null;
        }

        return $billing
            ->newSubscription(type: Addon::Members->value, prices: $priceId)
            ->quantity($data->quantity)
            ->withMetadata([
                'type' => 'addon_subscription',
                'addon' => Addon::Members->value,
                'quantity' => $data->quantity,
            ])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
