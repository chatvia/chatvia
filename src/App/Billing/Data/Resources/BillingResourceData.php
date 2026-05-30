<?php

namespace App\Billing\Data\Resources;

use App\Auth\Models\User;
use Exception;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingResourceData extends Data
{
    public function __construct(
        public BillingCurrentPackageData $current_package,
        public bool $on_trial,
        public ?string $trial_ends_at,
        public ?BillingSubscriptionData $subscription,
        public bool $subscribed,
        public bool $subscription_canceled,
        public ?BillingPendingChangeData $pending_plan_change,
    ) {}

    public static function fromUser(User $user): self
    {
        $currentPackage = $user->getCurrentPackage();
        $billing = $user->accountBilling();
        $subscription = $billing?->subscription();
        $subscriptionData = null;
        $pendingPlanChange = null;

        if ($subscription && $subscription->stripe_id) {
            try {
                $stripeSubscription = $subscription->asStripeSubscription();
                $subscriptionData = BillingSubscriptionData::fromSubscription($subscription, $stripeSubscription);
                $pendingPlanChange = BillingPendingChangeData::fromMetadata($stripeSubscription);
            } catch (Exception $e) {
                \Log::warning('Failed to fetch Stripe subscription data', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);
            }
        }

        return new self(
            current_package: BillingCurrentPackageData::fromArray($currentPackage),
            on_trial: (bool) $billing?->onTrial(),
            trial_ends_at: $billing?->trialEndsAt()?->toFormattedDateString(),
            subscription: $subscriptionData,
            subscribed: (bool) $billing?->subscribed(),
            subscription_canceled: (bool) $subscription?->canceled(),
            pending_plan_change: $pendingPlanChange,
        );
    }
}
