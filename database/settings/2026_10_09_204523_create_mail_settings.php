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
            $group->add('ses_key');
            $group->addEncrypted('ses_secret');
            $group->add('ses_region');
            $group->add('mailgun_domain');
            $group->addEncrypted('mailgun_secret');
            $group->add('mailgun_endpoint');
            $group->addEncrypted('postmark_token');
            $group->add('cloudflare_account_id');
            $group->addEncrypted('cloudflare_token');
            $group->add('from_address');
            $group->add('from_name');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'mail')->delete();
    }
};
