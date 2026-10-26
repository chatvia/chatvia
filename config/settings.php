<?php

declare(strict_types=1);

use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\BackupSettings;
use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\ChannelSettings;
use App\Admin\Settings\ConnectorSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SecuritySettings;
use App\Admin\Settings\SignInSettings;
use App\Admin\Settings\StorageSettings;
use App\Admin\Settings\WidgetSettings;
use App\Admin\Settings\WorkspaceSettings;
use Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

/*
|--------------------------------------------------------------------------
| Admin panel settings (spatie/laravel-settings)
|--------------------------------------------------------------------------
|
| The values an admin sets in the admin panel (Settings), stored in the
| `settings` table and copied into config when the app boots
| (App\Admin\Settings\SettingsConfig). Each class below has its own page and
| its own settings migration in database/settings.
|
*/

return [

    // Listed by hand: no folder scan when the app boots.
    'settings' => [
        SignInSettings::class,
        ChannelSettings::class,
        AiProviderSettings::class,
        AiSettings::class,
        KnowledgeSettings::class,
        ConnectorSettings::class,
        MailSettings::class,
        BrandSettings::class,
        WorkspaceSettings::class,
        PlatformSettings::class,
        ObservabilitySettings::class,
        StorageSettings::class,
        SecuritySettings::class,
        WidgetSettings::class,
        BackupSettings::class,
    ],

    'setting_class_path' => app_path('Admin/Settings'),

    'migrations_paths' => [
        database_path('settings'),
    ],

    'default_repository' => 'database',

    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => null,
            'connection' => null,
        ],
    ],

    'encoder' => null,
    'decoder' => null,

    // Every request and every queued job reads the settings, so they are
    // cached (secrets stay encrypted in the cache). A save updates the cache;
    // `migrate` clears it (App\Providers\AdminSettingsServiceProvider).
    'cache' => [
        'enabled' => true,
        'store' => null,
        'prefix' => null,
        'ttl' => null,
        'memo' => false,
    ],

    'global_casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        DateTimeZone::class => DateTimeZoneCast::class,
    ],

    'auto_discover_settings' => [],

    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
