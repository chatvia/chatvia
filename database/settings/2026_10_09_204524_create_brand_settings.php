<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\BrandSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('brand', function (SettingsBlueprint $group): void {
            $group->add('name');
            $group->add('website_url');
            $group->add('docs_url');
            $group->add('api_docs_url');
            $group->add('powered_by_enabled');
            $group->add('powered_by_label');
            $group->add('powered_by_url');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'brand')->delete();
    }
};
