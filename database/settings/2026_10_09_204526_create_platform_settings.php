<?php

declare(strict_types=1);

use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\PlatformSettings, empty (an empty value
 * keeps its config default until an admin saves one) except the program
 * paths: each holds the full path `which` finds on this server, as the Detect
 * button would, so the queue worker runs the same file even when its PATH
 * misses the folder. A program that is not found stays empty.
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
            $group->add('records_workers');
            $group->add('media_workers');
            $group->add('generation_workers');
            $group->add('voice_workers');

            $programs = app(ServerProgramFinder::class);

            foreach (ServerProgram::cases() as $program) {
                // An install must not stop over a server without `which`.
                $group->add($program->property(), rescue(fn (): ?string => $programs->detect($program), report: false));
            }

            $group->add('pdf_maker');
            $group->add('pdf_cloudflare_account_id');
            $group->addEncrypted('pdf_cloudflare_token');
            $group->add('pdf_gotenberg_url');
            $group->add('pdf_gotenberg_username');
            $group->addEncrypted('pdf_gotenberg_password');
            $group->add('logs_page_enabled');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'platform')->delete();
    }
};
