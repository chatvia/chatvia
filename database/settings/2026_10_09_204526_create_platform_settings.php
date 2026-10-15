<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\PlatformSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('platform', function (SettingsBlueprint $group): void {
            $group->add('setup_status');
            $group->add('default_locale');
            $group->add('widget_allowed_origins');
            $group->add('widget_script_url');
            $group->add('widget_stream_per_minute');
            $group->add('widget_stream_per_hour');
            $group->add('tracking_code');
            $group->addEncrypted('ip2location_api_key');
            $group->add('ip2location_endpoint');
            $group->add('horizon_notify_email');
            $group->add('chat_workers');
            $group->add('ingestion_workers');
            $group->add('analytics_workers');
            $group->add('media_workers');
            $group->add('voice_workers');
            $group->add('pdfinfo_path');
            $group->add('pdftotext_path');
            $group->add('pandoc_path');
            $group->add('catdoc_path');
            $group->add('catppt_path');
            $group->add('ffmpeg_path');
            $group->add('ffprobe_path');
            $group->add('weasyprint_path');
            $group->add('logs_page_enabled');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'platform')->delete();
    }
};
