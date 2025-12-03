<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Teams\TeamService;

class UserObserver
{
    public function created(User $user): void
    {
        // Use the default package (starter) for new users
        $defaultPackage = config('billing.default_package', 'starter');
        $quotaLimit = config("billing.packages.{$defaultPackage}.quota_limits.monthly");
        $fixed = config("billing.packages.{$defaultPackage}.quota_limits.fixed");

        // Only create quotas if they don't already exist (avoid duplication from webhook)
        if (! $user->quota()->exists()) {
            $user->quota()->create([
                'agents_limit' => $fixed['agents_limit'],
                'pages_limit' => $fixed['pages_limit'],
                'minutes_limit' => $fixed['minutes_limit'],
                'members_limit' => $fixed['members_limit'],
                'links_limit' => $fixed['links_limit'],
            ]);

            $user->addCredit(
                $quotaLimit,
                now()->addMonth(),
            );
        }

        // Create a default team for the user if they don't have one
        if (! $user->ownedTeams()->exists()) {
            $team = app(TeamService::class)->createDefaultTeam($user);

            // Set this as the user's primary team
            $user->updateQuietly(['team_id' => $team->id]);
        }
    }

    public function updated(User $user): void
    {
        if ($user->hasStripeId()) {
            $user->syncStripeCustomerDetails();
        }

        $user->memberOwner()->update(['name' => $user->name]);
    }

    public function deleted(User $user): void
    {
        if ($user->hasStripeId() && $user->subscribed()) {
            $user->cancelSubscription();
        }
    }

    public function restored(User $user): void {}

    public function forceDeleted(User $user): void
    {
        $this->deleted($user);
    }
}
