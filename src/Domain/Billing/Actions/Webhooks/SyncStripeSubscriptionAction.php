<?php

namespace Domain\Billing\Actions\Webhooks;

use Domain\Billing\Models\UserBilling;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

/**
 * Creates or refreshes the local Cashier subscription (and its items) from a
 * Stripe subscription id. It mirrors Cashier's own
 * `customer.subscription.created` handler, keyed on the Stripe id, so running
 * both is harmless.
 *
 * It exists because Stripe does not guarantee webhook order: Cashier's handler
 * silently does nothing when it arrives before the checkout has linked the
 * Stripe customer to a workspace. A Stripe failure propagates, so the webhook
 * answers with an error and Stripe retries.
 */
class SyncStripeSubscriptionAction
{
    public function execute(UserBilling $billing, string $subscriptionId): Subscription
    {
        $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscriptionId, [
            'expand' => ['items.data.price'],
        ]);

        $items = $stripeSubscription->items->data;
        $singleItem = count($items) === 1 ? $items[0] : null;

        return DB::transaction(function () use ($billing, $stripeSubscription, $items, $singleItem): Subscription {
            /** @var Subscription $subscription */
            $subscription = $billing->subscriptions()->updateOrCreate(
                ['stripe_id' => $stripeSubscription->id],
                [
                    'type' => $stripeSubscription->metadata['type'] ?? 'default',
                    'stripe_status' => $stripeSubscription->status,
                    'stripe_price' => $singleItem?->price->id,
                    'quantity' => $singleItem?->quantity,
                    'trial_ends_at' => $stripeSubscription->trial_end
                        ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                        : null,
                    'ends_at' => null,
                ],
            );

            foreach ($items as $item) {
                $subscription->items()->updateOrCreate(
                    ['stripe_id' => $item->id],
                    [
                        'stripe_product' => $item->price->product,
                        'stripe_price' => $item->price->id,
                        'quantity' => $item->quantity ?? null,
                    ],
                );
            }

            return $subscription;
        });
    }
}
