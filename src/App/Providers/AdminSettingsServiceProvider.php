<?php

namespace App\Providers;

use App\Admin\Settings\SettingsStore;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Applies the credentials saved in the admin panel (Settings) over the .env
 * values. Listed first in bootstrap/providers.php so the other app providers
 * already see the saved values when they boot (AppServiceProvider sets the
 * Stripe key and checks the webhook secret).
 */
class AdminSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsStore::class);
    }

    public function boot(): void
    {
        // `config:cache` (also run by `optimize`) writes the booted config to
        // bootstrap/cache/config.php. Applying here would put the decrypted
        // secrets in that file, in plain text.
        if ($this->app->runningConsoleCommand('config:cache', 'optimize')) {
            return;
        }

        $store = $this->app->make(SettingsStore::class);
        $store->apply();

        // A queue worker boots once and runs for hours: pick up changes made
        // in the panel since then before each job.
        Event::listen(JobProcessing::class, fn () => $store->apply());
    }
}
