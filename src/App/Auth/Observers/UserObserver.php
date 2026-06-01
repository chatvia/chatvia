<?php

namespace App\Auth\Observers;

use App\Auth\Models\User;
use App\Billing\Models\UserBilling;
use Support\Enums\User\UserType;

class UserObserver
{
    public function created(User $user): void
    {
        // Auto-provision a billing row for users that actually bill. Workspace
        // owners pay; admins may need stripe state too (admin accounts can hold
        // a subscription for fixtures/testing). Employees + chatters never
        // bill — no row, no orphan Cashier customer.
        if (in_array($user->type, [UserType::Workspace, UserType::Admin], true)) {
            UserBilling::firstOrCreate(['user_id' => $user->id]);
        }
    }

    public function updated(User $user): void
    {
        $billing = $user->billing;

        if ($billing?->hasStripeId()) {
            $billing->syncStripeCustomerDetails();
        }
    }

    public function deleted(User $user): void
    {
        $billing = $user->billing;

        if ($billing?->hasStripeId() && $billing->subscribed()) {
            $billing->subscription()?->cancel();
        }
    }

    public function restored(User $user): void {}

    public function forceDeleted(User $user): void
    {
        $this->deleted($user);
    }
}
