<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\MailSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('mail', function (SettingsBlueprint $group): void {
            $group->add('mailer');
            $group->add('smtp_host');
            $group->add('smtp_port');
            $group->add('smtp_scheme');
            $group->add('smtp_username');
            $group->addEncrypted('smtp_password');
            $group->addEncrypted('resend_api_key');
            $group->add('from_address');
            $group->add('from_name');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'mail')->delete();
    }
};
