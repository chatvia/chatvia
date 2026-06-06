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
        // Cashier mirrors only the user's name + email to Stripe (see
        // UserBilling::stripeName/stripeEmail). Without this guard every
        // `users` UPDATE — including the per-request `current_team_id` write
        // from User::ensureCurrentTeam() — fires a synchronous Stripe API
        // call, so a plain page render can round-trip to Stripe.
        if (! $user->wasChanged(['name', 'email'])) {
            return;
        }

        $billing = $user->billing;

        if ($billing?->hasStripeId()) {
            $billing->syncStripeCustomerDetails();
        }
    }

    public function deleting(User $user): void
    {
        // Must run on `deleting`, not `deleted`: `user_billing.user_id` and
        // `subscriptions.user_billing_id` both `ON DELETE CASCADE`, so by the
        // time `deleted` fires the billing row (and the Subscription->owner
        // relation it backs) is already gone and `cancel()` blows up trying
        // to reach Stripe through a null owner.
        $billing = $user->billing;

        if ($billing?->hasStripeId() && $billing->subscribed()) {
            $billing->subscription()?->cancel();
        }
    }

    public function restored(User $user): void {}
}
