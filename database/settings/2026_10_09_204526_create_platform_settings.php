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
            $group->add('setup_completed_at');
            $group->add('default_locale');
            $group->add('widget_allowed_origins');
            $group->add('widget_script_url');
            $group->add('widget_stream_per_minute');
            $group->add('widget_stream_per_hour');
            $group->add('plausible_domain');
            $group->add('plausible_script_url');
            $group->addEncrypted('ip2location_api_key');
            $group->add('ip2location_endpoint');
            $group->add('perplexity_base_url');
            $group->add('perplexity_agent_model');
            $group->add('perplexity_ask_model');
            $group->add('perplexity_research_model');
            $group->add('openweather_base_url');
            $group->add('exchange_rate_base_url');
            $group->add('horizon_notify_email');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'platform')->delete();
    }
};
