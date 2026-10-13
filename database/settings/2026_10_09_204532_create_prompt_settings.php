<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The row of App\Admin\Settings\PromptSettings, empty: every prompt uses its
 * file until an admin edits it.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('prompts', function (SettingsBlueprint $group): void {
            $group->add('overrides');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'prompts')->delete();
    }
};
