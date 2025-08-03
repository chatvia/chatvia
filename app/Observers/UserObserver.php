<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Billing\UserCreditSystem;

class UserObserver
{
    public function created(User $user): void
    {
        $freeQuotaLimit = config('billing.packages.free.quota_limits.monthly');
        $fixed = config('billing.packages.free.quota_limits.fixed');

        $user->update([
            'agents_instruction_limit' => $fixed['agents_instruction_limit'],
            'agents_knowledge_limit' => $fixed['agents_knowledge_limit'],
        ]);

        app(UserCreditSystem::class)->setUserId($user->id)->addCredit(
            $freeQuotaLimit,
            now()->addMonth(),
        );
    }

    public function updated(User $user): void
    {
        if ($user->hasStripeId()) {
            $user->syncStripeCustomerDetails();
        }
    }

    public function deleted(User $user): void
    {
        if ($user->hasStripeId() && $user->subscribed()) {
            $user->cancelSubscription();
        }
    }

    public function restored(User $user): void {}

    public function forceDeleted(User $user): void {}
}
