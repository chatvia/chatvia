<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Alert emails (long queue waits) go to the configured inbox only.
        // Without one, Horizon has nowhere to send them, so they stay off.
        $notifyEmail = config('horizon.notify_email');

        if (is_string($notifyEmail) && $notifyEmail !== '') {
            Horizon::routeMailNotificationsTo($notifyEmail);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return (bool) $user?->isAdmin();
        });
    }
}
