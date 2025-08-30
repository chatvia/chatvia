<?php

namespace App\Observers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserObserver
{
    public function created(User $user): void
    {
        $freeQuotaLimit = config('billing.packages.free.quota_limits.monthly');
        $fixed = config('billing.packages.free.quota_limits.fixed');

        $user->update([
            'agents_limit' => $fixed['agents_limit'],
            'pages_limit' => $fixed['pages_limit'],
            'minutes_limit' => $fixed['minutes_limit'],
            'members_limit' => $fixed['members_limit'],
            'links_limit' => $fixed['links_limit'],
        ]);

        $user
            ->addCredit(
                $freeQuotaLimit,
                now()->addMonth(),
            );

        Member::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'password' => Hash::make(Str::random(8)),
            'email_verified_at' => now(),
            'is_owner' => true,
        ]);
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
