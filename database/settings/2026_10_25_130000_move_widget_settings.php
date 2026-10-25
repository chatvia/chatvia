<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The widget's sites, script address and reply limits move from
 * App\Admin\Settings\PlatformSettings to WidgetSettings, with any value an
 * admin saved.
 */
return new class extends SettingsMigration
{
    private const array MOVES = [
        'platform.widget_allowed_origins' => 'widget.allowed_origins',
        'platform.widget_script_url' => 'widget.script_url',
        'platform.widget_stream_per_minute' => 'widget.stream_per_minute',
        'platform.widget_stream_per_hour' => 'widget.stream_per_hour',
    ];

    public function up(): void
    {
        foreach (self::MOVES as $from => $to) {
            $this->migrator->rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::MOVES as $from => $to) {
            $this->migrator->rename($to, $from);
        }
    }
};
