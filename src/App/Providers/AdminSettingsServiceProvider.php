<?php

namespace App\Providers;

use App\Admin\Settings\SettingsConfig;
use App\Admin\Settings\StorageDisks;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;
use Throwable;

/**
 * Puts the values saved in the admin panel (Settings) into config. It is the
 * first app provider (bootstrap/providers.php), so every other app provider
 * boots with the saved values, and so does everything after: middleware,
 * controllers, jobs.
 *
 * It applies in boot(), not earlier: the package providers have booted by
 * then, including the one that caches loaded settings. No package reads these
 * keys while it boots. Without a database (a build) or before `migrate`,
 * nothing is saved and every key keeps its config value.
 */
class AdminSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsConfig::class);
        $this->app->singleton(StorageDisks::class);
    }

    public function boot(): void
    {
        // `config:cache` (also run by `optimize`) writes the config of a fresh
        // app to bootstrap/cache/config.php. Applying would put the decrypted
        // secrets in that file, in plain text.
        if ($this->app->runningConsoleCommand('config:cache', 'optimize')) {
            return;
        }

        $settings = $this->app->make(SettingsConfig::class);
        $settings->apply();

        // A queue worker boots once and runs for hours: pick up changes made
        // in the panel since then before each job. A save applies at once, so
        // the rest of that request sees the new values too.
        Event::listen(JobProcessing::class, fn () => $settings->apply());
        Event::listen(SettingsSaved::class, fn () => $settings->apply());

        // A settings migration writes rows the cache does not know about.
        Event::listen(MigrationsEnded::class, function (): void {
            try {
                foreach ($this->app->make(SettingsCacheFactory::class)->all() as $cache) {
                    $cache->clear();
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
