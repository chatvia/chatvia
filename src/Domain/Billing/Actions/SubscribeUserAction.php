<?php

namespace Domain\Billing\Actions;

use Domain\Billing\Data\SubscribeData;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\SubscriptionBuilder;

class SubscribeUserAction
{
    public function execute(SubscribeData $data): ?Checkout
    {
        $plans = config('billing.packages');

        if (! isset($plans[$data->package])) {
            return null;
        }

        $stripePriceId = $data->yearly
            ? $plans[$data->package]['stripe_yearly_price_id']
            : $plans[$data->package]['stripe_monthly_price_id'];

        return $data->user
            ->newSubscription(type: 'default', prices: $stripePriceId)
            ->withMetadata([
                'id' => strtolower($data->package),
                'type' => 'subscription',
                'from_package' => $data->user->plan,
                'to_package' => strtolower($data->package),
                'is_yearly' => $data->yearly,
                'user_subscribed' => $data->user->subscribed(),
            ])
            ->when(config('billing.trial.days') !== 0, function (SubscriptionBuilder $subscription): void {
                $subscription->trialDays((int) config('billing.trial.days'));
            })
            ->allowPromotionCodes()
            ->alwaysInvoice()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
