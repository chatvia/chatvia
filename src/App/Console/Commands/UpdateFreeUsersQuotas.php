<?php

namespace App\Console\Commands;

use App\Auth\Models\User;
use Illuminate\Console\Command;

class UpdateFreeUsersQuotas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-free-users-quotas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update free users monthly quotas';

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
            ->whereHas('credit', function ($query) {
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
