<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\WidgetSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('widget', function (SettingsBlueprint $group): void {
            $group->add('ai_disclosure');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'widget')->delete();
    }
};
