<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\SubscribeCustomDomainData;
use Laravel\Cashier\Checkout;
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

        $existing = $billing->subscription('custom_domains');

        if ($existing && $existing->active()) {
            // Wire the parent relation so Cashier's `updateQuantity()` doesn't
            // lazy-load `owner` (forbidden by Model::shouldBeStrict in dev).
            $existing->setRelation('owner', $billing);
            $existing->updateQuantity($data->quantity);

            $data->user->quota()->updateOrCreate([], ['extra_custom_domains_limit' => $data->quantity]);

            return null;
        }

        return $billing
            ->newSubscription(type: 'custom_domains', prices: $priceId)
            ->quantity($data->quantity)
            ->withMetadata([
                'type' => 'addon_subscription',
                'addon' => 'custom_domains',
                'quantity' => $data->quantity,
            ])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
