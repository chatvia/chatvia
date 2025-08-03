<?php

namespace App\Console\Commands;

use App\Models\User;
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
        $quotaLimit = config('billing.packages.free.quota_limits.monthly');

        $freeUsers = User::query()
            ->whereHas('credit', function ($query) {
                $query->where('ends_at', '<=', now()->addDay());
            })
            ->orWhereDoesntHave('credit')
            ->get();

        /** @var User $user */
        foreach ($freeUsers as $user) {
            $user->creditSystem()->addCredit($quotaLimit, now()->addMonth());
        }
    }
}
