<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\Addon;
use Laravel\Cashier\Checkout;
use RuntimeException;

/**
 * Creates a Cashier subscription of type `remove_branding` — a flat monthly
 * add-on that hides the "Powered by Chatvia" badge across the workspace's
 * widget and hosted chat. A boolean add-on: always quantity 1, with no
 * `user_quotas` mirror — the entitlement is read live via
 * {@see User::canHideBranding()}.
 */
class SubscribeRemoveBrandingAction
{
    public function execute(User $user): ?Checkout
    {
        $priceId = config('billing.addons.remove_branding.stripe_price_id');

        if (! $priceId) {
            throw new RuntimeException(
                'Remove Branding addon is not configured. Set STRIPE_REMOVE_BRANDING_PRICE_ID in your environment.'
            );
        }

        $billing = $user->accountBillingOrFail();

        // Already subscribed → nothing to do; the badge is already hidden.
        $existing = $billing->ownedSubscription(Addon::RemoveBranding->value);

        if ($existing && $existing->active()) {
            return null;
        }

        return $billing
            ->newSubscription(type: Addon::RemoveBranding->value, prices: $priceId)
            ->withMetadata([
                'type' => 'addon_subscription',
                'addon' => Addon::RemoveBranding->value,
                'quantity' => 1,
            ])
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
