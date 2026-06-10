<?php

namespace Domain\Billing\Actions\Subscriptions;

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

        // Contact-sales packages (Enterprise) have no Stripe price — they are
        // provisioned manually through the demo/sales flow, never self-serve.
        if ($plans[$data->package]['contact_sales'] ?? false) {
            return null;
        }

        $stripePriceId = $data->yearly
            ? $plans[$data->package]['stripe_yearly_price_id']
            : $plans[$data->package]['stripe_monthly_price_id'];

        // Provision the account's billing row on demand. Seeded accounts (and
        // any user created before the UserObserver provisioned one) reach
        // checkout without a `user_billing` row, so accountBilling() is null.
        // Cashier needs the Billable row to exist before it can open checkout;
        // firstOrCreate is idempotent for everyone who already has one.
        $billing = $data->user->workspace()->billing()->firstOrCreate([]);

        // Push our local email/name onto the Stripe customer BEFORE the
        // checkout session is created. Without this, customers that were
        // created without an email (or before {@see UserBilling::stripeEmail()}
        // was added) show an empty email field in Stripe Checkout because
        // Cashier passes `customer: <id>` and Stripe reads the email from
        // the customer record. createAsStripeCustomer fires only when
        // stripe_id is null — for everyone else we sync explicitly.
        if ($billing->hasStripeId()) {
            $billing->syncStripeCustomerDetails();
        }

        return $billing
            ->newSubscription(type: 'default', prices: $stripePriceId)
            ->withMetadata([
                'id' => strtolower($data->package),
                'type' => 'subscription',
                'from_package' => $data->user->plan,
                'to_package' => strtolower($data->package),
                'is_yearly' => $data->yearly,
                'user_subscribed' => (bool) $billing->subscribed(),
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
