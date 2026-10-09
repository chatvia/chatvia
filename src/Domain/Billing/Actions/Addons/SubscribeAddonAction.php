<?php

namespace Domain\Billing\Actions\Addons;

use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Subscriptions\SubscribeAddonData;
use Domain\Billing\Enums\PurchaseType;
use Domain\Billing\Exceptions\AddonNotConfiguredException;
use Domain\Billing\Models\UserBilling;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;

/**
 * Buys an add-on subscription (extra agents, members, custom domains, remove
 * branding) as its own Cashier subscription whose `type` is the add-on value.
 *
 * When the workspace already has the add-on, a quantity add-on changes its
 * quantity in place and mirrors it onto the quota column right away (the
 * webhook re-syncs it later); a yes/no add-on has nothing to change. Both
 * return null because no checkout is needed. Otherwise a Stripe Checkout is
 * returned for the controller to redirect to.
 *
 * @throws AddonNotConfiguredException when the add-on has no Stripe price
 */
class SubscribeAddonAction
{
    public function execute(SubscribeAddonData $data): ?Checkout
    {
        $addon = $data->addon;
        $priceId = $addon->stripePriceId() ?? throw AddonNotConfiguredException::for($addon);
        $quantity = $addon->hasQuantity() ? $data->quantity : 1;

        $billing = $data->user->accountBillingOrFail();
        $existing = $billing->ownedSubscription($addon->value);

        if ($existing instanceof Subscription && $existing->active()) {
            $this->changeQuantity($data, $existing, $quantity);

            return null;
        }

        return $this->checkout($billing, $data, $priceId, $quantity);
    }

    private function changeQuantity(SubscribeAddonData $data, Subscription $subscription, int $quantity): void
    {
        $quotaColumn = $data->addon->quotaColumn();

        if (! $data->addon->hasQuantity() || $quotaColumn === null) {
            return;
        }

        $subscription->updateQuantity($quantity);
        $data->user->quota()->updateOrCreate([], [$quotaColumn => $quantity]);
    }

    private function checkout(UserBilling $billing, SubscribeAddonData $data, string $priceId, int $quantity): Checkout
    {
        $builder = $billing->newSubscription(type: $data->addon->value, prices: $priceId);

        if ($data->addon->hasQuantity()) {
            $builder->quantity($quantity);
        }

        return $builder
            ->withMetadata([
                StripeMetadata::TYPE => PurchaseType::AddonSubscription->value,
                StripeMetadata::ADDON => $data->addon->value,
                StripeMetadata::QUANTITY => $quantity,
            ])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => $data->urls->successUrl,
                'cancel_url' => $data->urls->cancelUrl,
            ]);
    }
}
