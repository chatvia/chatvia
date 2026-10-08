<?php

namespace Domain\Billing\Data\Resources\Subscription;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Resources\Package\BillingCurrentPackageData;
use Exception;
use Laravel\Cashier\Subscription;
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
        public BillingAddonsData $addons,
    ) {}

    public static function fromUser(User $user): self
    {
        $currentPackage = $user->getCurrentPackage();
        $billing = $user->accountBilling();
        $subscription = $billing?->subscription();
        $subscriptionData = null;
        $pendingPlanChange = null;

        if ($subscription instanceof Subscription && $subscription->stripe_id) {
            // Cashier's `Subscription::asStripeSubscription` reaches for
            // `$this->owner` to get the Stripe client. That relation is the
            // inverse of the one we just used to fetch the subscription — we
            // already have the UserBilling row in `$billing`. Set it
            // explicitly so the call doesn't trip strict-lazy-loading.
            $subscription->setRelation('owner', $billing);

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
            addons: BillingAddonsData::fromUser($user),
        );
    }
}
