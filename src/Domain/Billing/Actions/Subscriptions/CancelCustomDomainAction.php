<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;

class CancelCustomDomainAction
{
    public function execute(User $user): void
    {
        $billing = $user->accountBillingOrFail();
        $subscription = $billing->subscription('custom_domains');

        if ($subscription && ! $subscription->canceled()) {
            // Wire the parent relation so Cashier's `cancelNow()` doesn't
            // lazy-load `owner` (forbidden by Model::shouldBeStrict in dev).
            $subscription->setRelation('owner', $billing);
            $subscription->cancelNow();
        }

        $user->quota()->updateOrCreate([], ['extra_custom_domains_limit' => 0]);
    }
}
