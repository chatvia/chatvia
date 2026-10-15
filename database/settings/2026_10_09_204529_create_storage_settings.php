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
            $group->add('s3_endpoint');
            $group->add('s3_region');
            $group->add('s3_key');
            $group->addEncrypted('s3_secret');
            $group->add('s3_path_style');
            $group->add('s3_bucket');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'storage')->delete();
    }
};
