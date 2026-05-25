<?php

namespace App\Console\Commands;

use App\Auth\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Update free users monthly quotas')]
#[Signature('app:update-free-users-quotas')]
class UpdateFreeUsersQuotas extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Since free plan is removed, this command now handles trial users
        // Only refresh credits for users who have an active subscription or trial

        $defaultPackage = config('billing.default_package', 'starter');
        $quotaLimit = config("billing.packages.{$defaultPackage}.quota_limits.monthly");

        $users = User::query()
            ->whereHas('credit', function ($query): void {
                $query->where('ends_at', '<=', now()->addDay());
            })
            ->orWhereDoesntHave('credit')
            ->get();

        /** @var User $user */
        foreach ($users as $user) {
            // Only refresh credits if user has active subscription or is on trial
            if ($user->subscribed('default') || $user->onTrial()) {
                $user->creditSystem()->addCredit($quotaLimit, now()->addMonth());
            }
        }
    }
}
