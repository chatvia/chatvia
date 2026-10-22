<?php

declare(strict_types=1);

namespace App\Admin\Status;

use App\Admin\Settings\SettingsConfig;
use App\Admin\Status\Data\EnvironmentFactData;
use App\Admin\Status\Data\EnvironmentGroupData;
use Composer\InstalledVersions;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * What `php artisan about` tells, for the Status page: the application, the
 * versions of PHP and the main packages, which caches are on, and the
 * drivers in use. All of it is read in this process, without running a
 * command.
 */
final readonly class EnvironmentReport
{
    /** The packages whose versions the report shows, by name. */
    private const array PACKAGES = [
        'Laravel' => 'laravel/framework',
        'Filament' => 'filament/filament',
        'Livewire' => 'livewire/livewire',
        'Horizon' => 'laravel/horizon',
        'Pulse' => 'laravel/pulse',
        'Reverb' => 'laravel/reverb',
    ];

    public function __construct(private Application $app) {}

    /**
     * @return list<EnvironmentGroupData>
     */
    public function groups(): array
    {
        return [
            new EnvironmentGroupData(__('Application'), Heroicon::OutlinedWindow, $this->application()),
            new EnvironmentGroupData(__('Versions'), Heroicon::OutlinedTag, $this->versions()),
            new EnvironmentGroupData(__('Caches'), Heroicon::OutlinedRocketLaunch, $this->caches()),
            new EnvironmentGroupData(__('Drivers'), Heroicon::OutlinedCpuChip, $this->drivers()),
        ];
    }

    /**
     * @return list<EnvironmentFactData>
     */
    private function application(): array
    {
        $debug = (bool) config('app.debug');
        $down = $this->app->isDownForMaintenance();

        return [
            new EnvironmentFactData(__('Address'), (string) config('app.url')),
            new EnvironmentFactData(__('Environment'), $this->app->environment(), $this->app->isProduction() ? 'success' : 'gray'),
            // Debug mode shows error details, with secrets, to anyone.
            new EnvironmentFactData(__('Debug mode'), $debug ? __('On') : __('Off'), $debug && $this->app->isProduction() ? 'danger' : null),
            new EnvironmentFactData(__('Maintenance mode'), $down ? __('On') : __('Off'), $down ? 'warning' : null),
            new EnvironmentFactData(__('Time zone'), (string) config('app.timezone')),
            new EnvironmentFactData(__('Language'), (string) config('app.locale')),
        ];
    }

    /**
     * @return list<EnvironmentFactData>
     */
    private function versions(): array
    {
        return [
            new EnvironmentFactData('PHP', PHP_VERSION),
            ...array_map(
                fn (string $name, string $package): EnvironmentFactData => new EnvironmentFactData($name, InstalledVersions::isInstalled($package)
                    ? ltrim((string) InstalledVersions::getPrettyVersion($package), 'v')
                    : __('Not installed')),
                array_keys(self::PACKAGES),
                self::PACKAGES,
            ),
        ];
    }

    /**
     * @return list<EnvironmentFactData>
     */
    private function caches(): array
    {
        $views = File::glob($this->app->storagePath('framework/views/*.php')) !== [];
        $settings = $this->settingsAreCached();

        return [
            $this->cache(__('Config'), $this->app->configurationIsCached()),
            $this->cache(__('Routes'), $this->app->routesAreCached()),
            $this->cache(__('Events'), $this->app->eventsAreCached()),
            $this->cache(__('Views'), $views),
            new EnvironmentFactData(
                __('Settings'),
                $settings ? __('Cached') : __('Not cached'),
                $settings ? 'success' : null,
                action: 'reloadSettings',
                actionLabel: __('Reload the saved settings and AI models'),
            ),
        ];
    }

    /**
     * Whether every admin settings class is in the settings cache. A copy
     * left from before a save is the reason the row can be reloaded.
     */
    private function settingsAreCached(): bool
    {
        if (! config('settings.cache.enabled')) {
            return false;
        }

        $store = Cache::store(config('settings.cache.store'));
        $prefix = filled(config('settings.cache.prefix')) ? config('settings.cache.prefix').'.' : '';

        return collect($this->app->make(SettingsConfig::class)->classes())
            ->every(fn (string $class): bool => $store->has($prefix.'settings.'.$class::cacheKey()));
    }

    /**
     * @return list<EnvironmentFactData>
     */
    private function drivers(): array
    {
        $drivers = [
            __('Broadcasting') => config('broadcasting.default'),
            __('Cache') => config('cache.default'),
            __('Database') => config('database.default'),
            __('Logs') => $this->logChannels(),
            __('Mail') => config('mail.default'),
            __('Queue') => config('queue.default'),
            __('Session') => config('session.driver'),
        ];

        return array_map(
            fn (string $label, mixed $driver): EnvironmentFactData => new EnvironmentFactData($label, (string) $driver),
            array_keys($drivers),
            $drivers,
        );
    }

    /** The log channel, or the channels a stack writes to. */
    private function logChannels(): string
    {
        $channel = (string) config('logging.default');

        if (config("logging.channels.{$channel}.driver") !== 'stack') {
            return $channel;
        }

        return Str::of($channel)->append(' (', implode(', ', (array) config("logging.channels.{$channel}.channels")), ')')->toString();
    }

    private function cache(string $label, bool $cached): EnvironmentFactData
    {
        return new EnvironmentFactData($label, $cached ? __('Cached') : __('Not cached'), $cached ? 'success' : null);
    }
}
