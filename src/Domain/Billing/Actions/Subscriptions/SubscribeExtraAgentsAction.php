<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\SubscribeExtraAgentsData;
use Laravel\Cashier\Checkout;
use RuntimeException;

/**
 * Creates a Cashier subscription of type `extra_agents`. The plan's base
 * `agents_limit` is left untouched on `user_quotas`; we mirror the
 * subscription quantity onto `extra_agents_limit` via the webhook.
 */
class SubscribeExtraAgentsAction
{
    public function execute(SubscribeExtraAgentsData $data): ?Checkout
    {
        $priceId = config('billing.addons.extra_agents.stripe_price_id');

        if (! $priceId) {
            throw new RuntimeException(
                'Extra Agents addon is not configured. Set STRIPE_EXTRA_AGENT_PRICE_ID in your environment.'
            );
        }

        $billing = $data->user->accountBillingOrFail();

        // Existing subscription → adjust quantity in place; no checkout needed.
        $existing = $billing->subscription('extra_agents');

        if ($existing && $existing->active()) {
            // Cashier's `updateQuantity()` reaches for `$this->owner` to
            // fetch the Stripe client. We already have the UserBilling row,
            // so wire it on directly to avoid strict-lazy-loading.
            $existing->setRelation('owner', $billing);
            $existing->updateQuantity($data->quantity);

            $data->user->quota()->updateOrCreate([], ['extra_agents_limit' => $data->quantity]);

            return null;
        }

        return $billing
            ->newSubscription(type: 'extra_agents', prices: $priceId)
            ->quantity($data->quantity)
            ->withMetadata([
                'type' => 'addon_subscription',
                'addon' => 'extra_agents',
                'quantity' => $data->quantity,
            ])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
