<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\SubscribeExtraAgentsData;
use Domain\Billing\Enums\Addon;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;
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
        $existing = $billing->ownedSubscription(Addon::ExtraAgents->value);

        if ($existing instanceof Subscription && $existing->active()) {
            $existing->updateQuantity($data->quantity);

            $data->user->quota()->updateOrCreate([], [Addon::ExtraAgents->quotaColumn() => $data->quantity]);

            return null;
        }

        return $billing
            ->newSubscription(type: Addon::ExtraAgents->value, prices: $priceId)
            ->quantity($data->quantity)
            ->withMetadata([
                'type' => 'addon_subscription',
                'addon' => Addon::ExtraAgents->value,
                'quantity' => $data->quantity,
            ])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
