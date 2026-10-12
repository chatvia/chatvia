<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\SignInSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('sign_in', function (SettingsBlueprint $group): void {
            $group->add('google_client_id');
            $group->addEncrypted('google_client_secret');
            $group->add('google_redirect');
            $group->add('microsoft_client_id');
            $group->addEncrypted('microsoft_client_secret');
            $group->add('microsoft_redirect');
            $group->add('github_client_id');
            $group->addEncrypted('github_client_secret');
            $group->add('github_redirect');
            $group->add('slack_client_id');
            $group->addEncrypted('slack_client_secret');
            $group->add('slack_redirect');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'sign_in')->delete();
    }
};
