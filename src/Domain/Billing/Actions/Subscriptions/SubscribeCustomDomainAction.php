<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\SubscribeCustomDomainData;
use Domain\Billing\Enums\Addon;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;
use RuntimeException;

/**
 * Creates a Cashier subscription of type `custom_domains`. Mirrors quantity
 * onto `user_quotas.extra_custom_domains_limit` via the Stripe webhook.
 */
class SubscribeCustomDomainAction
{
    public function execute(SubscribeCustomDomainData $data): ?Checkout
    {
        $priceId = config('billing.addons.custom_domains.stripe_price_id');

        if (! $priceId) {
            throw new RuntimeException(
                'Custom Domain addon is not configured. Set STRIPE_CUSTOM_DOMAIN_PRICE_ID in your environment.'
            );
        }

        $billing = $data->user->accountBillingOrFail();

        $existing = $billing->ownedSubscription(Addon::CustomDomains->value);

        if ($existing instanceof Subscription && $existing->active()) {
            $existing->updateQuantity($data->quantity);

            $data->user->quota()->updateOrCreate([], [Addon::CustomDomains->quotaColumn() => $data->quantity]);

            return null;
        }

        return $billing
            ->newSubscription(type: Addon::CustomDomains->value, prices: $priceId)
            ->quantity($data->quantity)
            ->withMetadata([
                'type' => 'addon_subscription',
                'addon' => Addon::CustomDomains->value,
                'quantity' => $data->quantity,
            ])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
