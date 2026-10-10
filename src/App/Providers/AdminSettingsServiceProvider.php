<?php

namespace App\Providers;

use App\Admin\Settings\SettingsStore;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Puts the values saved in the admin panel (Settings) into config, once every
 * provider has registered and before any provider boots (package providers
 * included). Listed first in bootstrap/providers.php, so its booting callback
 * runs before the ones the other app providers add: AppServiceProvider
 * decides there whether Cashier adds its routes, from `billing.enabled`.
 * Every boot() then sees the saved values (AppServiceProvider sets the Stripe
 * key), and so does everything after: the Entitlements driver, middleware,
 * controllers.
 *
 * It cannot apply inside register(): the cache is a deferred service, which
 * can be resolved only after every provider has registered. Without a
 * database (a build) or before `migrate`, nothing is saved and every field
 * gets its default.
 */
class AdminSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsStore::class);

        // `config:cache` (also run by `optimize`) writes the config of a fresh
        // app to bootstrap/cache/config.php. Applying would put the decrypted
        // secrets in that file, in plain text.
        if ($this->app->runningConsoleCommand('config:cache', 'optimize')) {
            return;
        }

        $this->app->booting(fn () => $this->app->make(SettingsStore::class)->apply());
    }

    public function boot(): void
    {
        if ($this->app->runningConsoleCommand('config:cache', 'optimize')) {
            return;
        }

        // A queue worker boots once and runs for hours: pick up changes made
        // in the panel since then before each job.
        Event::listen(JobProcessing::class, fn () => $this->app->make(SettingsStore::class)->apply());
    }
}
