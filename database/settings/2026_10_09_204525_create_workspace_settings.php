<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\WorkspaceSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('workspaces', function (SettingsBlueprint $group): void {
            $group->add('registration_enabled');
            $group->add('new_workspace_message_limit');
            $group->add('retention_days');
            $group->add('retention_floor_days');
            $group->add('notification_retention_days');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'workspaces')->delete();
    }
};
