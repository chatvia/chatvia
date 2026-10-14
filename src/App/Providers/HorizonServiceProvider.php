<?php

namespace App\Providers;

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Override;

/**
 * Horizon (the queue dashboard): alert routing and who may open it outside the
 * local environment — staff signed in to the admin panel
 * ({@see StaffToolsAccess}).
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    #[Override]
    public function boot(): void
    {
        parent::boot();

        // Alert emails (long queue waits) go to the configured inbox only.
        // Without one, Horizon has nowhere to send them, so they stay off.
        $notifyEmail = config('horizon.notify_email');

        if (filled($notifyEmail)) {
            Horizon::routeMailNotificationsTo($notifyEmail);
        }
    }

    /**
     * The web-guard user Horizon passes in is ignored: only the admin panel's
     * own session counts.
     */
    #[Override]
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows());
    }
}
