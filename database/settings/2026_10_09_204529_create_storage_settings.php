<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\StorageSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('storage', function (SettingsBlueprint $group): void {
            $group->add('driver');
            $group->add('s3_private_bucket');
            $group->add('s3_private_endpoint');
            $group->add('s3_private_region');
            $group->add('s3_private_key');
            $group->addEncrypted('s3_private_secret');
            $group->add('s3_private_path_style');
            $group->add('s3_public_bucket');
            $group->add('s3_public_url');
            $group->add('s3_public_endpoint');
            $group->add('s3_public_region');
            $group->add('s3_public_key');
            $group->addEncrypted('s3_public_secret');
            $group->add('s3_public_path_style');
            $group->add('s3_backup_bucket');
            $group->add('s3_backup_endpoint');
            $group->add('s3_backup_region');
            $group->add('s3_backup_key');
            $group->addEncrypted('s3_backup_secret');
            $group->add('s3_backup_path_style');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'storage')->delete();
    }
};
