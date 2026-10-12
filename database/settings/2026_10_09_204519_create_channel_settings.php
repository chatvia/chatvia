<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\ChannelSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('channels', function (SettingsBlueprint $group): void {
            $group->add('messenger_app_id');
            $group->addEncrypted('messenger_app_secret');
            $group->addEncrypted('messenger_webhook_verify_token');
            $group->add('messenger_config_id');
            $group->add('messenger_redirect');
            $group->add('instagram_app_id');
            $group->addEncrypted('instagram_app_secret');
            $group->addEncrypted('instagram_webhook_verify_token');
            $group->add('instagram_config_id');
            $group->add('instagram_redirect');
            $group->add('whatsapp_app_id');
            $group->addEncrypted('whatsapp_app_secret');
            $group->addEncrypted('whatsapp_webhook_verify_token');
            $group->add('whatsapp_config_id');
            $group->addEncrypted('whatsapp_register_pin');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'channels')->delete();
    }
};
