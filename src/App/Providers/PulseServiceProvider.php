<?php

declare(strict_types=1);

namespace App\Providers;

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

/**
 * Pulse (the app's health dashboard at /pulse): who may open it — staff
 * signed in to the admin panel ({@see StaffToolsAccess}), like Horizon — and
 * how a person shows on its cards. Pulse's own default loads a Gravatar
 * picture for everyone without a profile photo, which would send their email
 * hashes to an outside service.
 */
class PulseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The web-guard user Pulse passes in is ignored: only the admin
        // panel's own session counts.
        Gate::define('viewPulse', fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows());

        Pulse::user(fn (User $user): array => array_filter([
            'name' => $user->name,
            'extra' => $user->email,
            'avatar' => $user->avatar,
        ]));
    }
}
