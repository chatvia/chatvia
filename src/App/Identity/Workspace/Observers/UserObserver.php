<?php

namespace App\Identity\Workspace\Observers;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Models\UserQuota;
use Domain\Membership\Team\Services\TeamService;
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

            // Seed with starter-plan fixed limits so capability checks
            // (canAddWorkspaceUser, canCreateTeam, …) have something to read
            // against before the first Stripe webhook lands.
            UserQuota::firstOrCreate(
                ['user_id' => $user->id],
                config('billing.packages.starter.quota_limits.fixed', [])
            );

            // Every workspace owner needs a default team so dashboard routes
            // (gated by has_team_permission) don't 404 immediately after
            // registration. Idempotent: skips if the user already has one.
            if ($user->ownedTeams()->doesntExist()) {
                app(TeamService::class)->createDefaultTeam($user);
            }
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
