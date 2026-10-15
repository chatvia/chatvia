<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\ConnectorSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('connectors', function (SettingsBlueprint $group): void {
            $group->add('github_client_id');
            $group->addEncrypted('github_client_secret');
            $group->add('gmail_client_id');
            $group->addEncrypted('gmail_client_secret');
            $group->add('google_calendar_client_id');
            $group->addEncrypted('google_calendar_client_secret');
            $group->add('google_drive_client_id');
            $group->addEncrypted('google_drive_client_secret');
            $group->add('slack_client_id');
            $group->addEncrypted('slack_client_secret');
            $group->add('hubspot_client_id');
            $group->addEncrypted('hubspot_client_secret');
            $group->add('box_client_id');
            $group->addEncrypted('box_client_secret');
            $group->add('asana_client_id');
            $group->addEncrypted('asana_client_secret');
            $group->add('perplexity_base_url');
            $group->add('perplexity_agent_model');
            $group->add('perplexity_ask_model');
            $group->add('perplexity_research_model');
            $group->add('openweather_base_url');
            $group->add('exchange_rate_base_url');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'connectors')->delete();
    }
};
