<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Subscriptions\SubscribeData;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\PurchaseType;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\SubscriptionBuilder;

/**
 * Opens a Stripe Checkout for a self-serve plan (with the configured trial).
 * Returns null for a plan that does not exist, is archived, or is sold through
 * sales only (Enterprise has no Stripe price).
 */
class SubscribeUserAction
{
    public function __construct(
        private readonly BillingCatalog $catalog,
    ) {}

    public function execute(SubscribeData $data): ?Checkout
    {
        $plan = $this->catalog->plan($data->package);

        if (! $plan instanceof BillingPlan || ! $plan->is_active || $plan->contact_sales) {
            return null;
        }

        $stripePriceId = $plan->stripePriceId(BillingInterval::fromYearly($data->yearly));
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
