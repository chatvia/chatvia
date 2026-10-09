<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Subscriptions\SubscribeData;
use Domain\Billing\Enums\PurchaseType;
use Domain\Billing\Models\UserBilling;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\SubscriptionBuilder;

/**
 * Opens a Stripe Checkout for a self-serve plan (with the configured trial).
 * Returns null for a plan that is not configured or is sold through sales
 * only (Enterprise has no Stripe price).
 */
class SubscribeUserAction
{
    public function execute(SubscribeData $data): ?Checkout
    {
        $plan = ((array) config('billing.packages', []))[$data->package] ?? null;

        if (! is_array($plan) || ($plan['contact_sales'] ?? false)) {
            return null;
        }

        $stripePriceId = $data->yearly ? $plan['stripe_yearly_price_id'] : $plan['stripe_monthly_price_id'];
        $package = strtolower($data->package);

        // Seeded accounts (and any created before the UserObserver provisioned
        // one) reach checkout without a billing row; Cashier needs it first.
        $billing = $data->user->workspace()->billing()->firstOrCreate([]);

        $this->syncCustomerDetails($billing);

        return $billing
            ->newSubscription(type: 'default', prices: $stripePriceId)
            ->withMetadata([
                StripeMetadata::ID => $package,
                StripeMetadata::TYPE => PurchaseType::Subscription->value,
                StripeMetadata::FROM_PACKAGE => $data->user->plan,
                StripeMetadata::TO_PACKAGE => $package,
                StripeMetadata::IS_YEARLY => $data->yearly,
                'user_subscribed' => (bool) $billing->subscribed(),
            ])
            ->when(config('billing.trial.days') !== 0, function (SubscriptionBuilder $subscription): void {
                $subscription->trialDays((int) config('billing.trial.days'));
            })
            ->allowPromotionCodes()
            ->alwaysInvoice()
            ->checkout([
                'success_url' => $data->urls->successUrl,
                'cancel_url' => $data->urls->cancelUrl,
            ]);
    }

    /**
     * Push our email and name onto an existing Stripe customer before the
     * checkout opens. Cashier passes `customer: <id>` and Stripe reads the
     * email from the customer record, so a customer created without one would
     * show an empty email field. A new customer gets them from
     * {@see UserBilling::stripeEmail()} when Cashier creates it.
     */
    private function syncCustomerDetails(UserBilling $billing): void
    {
        if ($billing->hasStripeId()) {
            $billing->syncStripeCustomerDetails();
        }
    }
}
