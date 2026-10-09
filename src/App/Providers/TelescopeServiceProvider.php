<?php

namespace App\Providers;

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Telescope (request and job debugging): which entries are recorded, which
 * request details are hidden, and who may open it outside the local
 * environment — staff signed in to the admin panel ({@see StaffToolsAccess}).
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        // No isRequest()/isClientRequest() outside local — never persist full prod request payloads.
        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * The web-guard user Telescope passes in is ignored: only the admin
     * panel's own session counts.
     */
    #[\Override]
    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows());
    }
}
