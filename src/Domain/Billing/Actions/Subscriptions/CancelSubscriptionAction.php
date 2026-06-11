<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Laravel\Cashier\Cashier;

class CancelSubscriptionAction
{
    public function execute(User $user): void
    {
        $subscription = $user->accountBilling()?->subscription();

        if ($subscription) {
            $currentMetadata = $subscription->asStripeSubscription()->metadata->toArray();

            if (isset($currentMetadata['schedule_id'])) {
                Cashier::stripe()->subscriptionSchedules->retrieve($currentMetadata['schedule_id'])->release();
            }

            if (isset($currentMetadata['pending_change']) && $currentMetadata['pending_change'] === 'true') {
                $subscription->updateStripeSubscription([
                    'metadata' => array_merge($currentMetadata, [
                        'pending_change' => 'false',
                        'from_package' => null,
                        'to_package' => null,
                        'new_price_id' => null,
                        'scheduled_at' => null,
                        'cancelled_at' => now()->toISOString(),
                        'cancellation_reason' => 'subscription_cancelled',
                    ]),
                ]);
            }
        }

        $user->cancelSubscription();
    }
}
