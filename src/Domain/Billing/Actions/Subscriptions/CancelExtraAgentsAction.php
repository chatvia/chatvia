<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\Addon;

class CancelExtraAgentsAction
{
    public function execute(User $user): void
    {
        $billing = $user->accountBillingOrFail();
        $subscription = $billing->ownedSubscription(Addon::ExtraAgents->value);

        if ($subscription && ! $subscription->canceled()) {
            $subscription->cancelNow();
        }

        $user->quota()->updateOrCreate([], [Addon::ExtraAgents->quotaColumn() => 0]);
    }
}
