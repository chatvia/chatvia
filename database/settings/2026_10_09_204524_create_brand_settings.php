<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The row of App\Admin\Settings\BrandSettings, empty: an empty value keeps
 * its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('brand', function (SettingsBlueprint $group): void {
            $group->add('powered_by_enabled');
            $group->addEncrypted('license_key');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'brand')->delete();
    }
};
