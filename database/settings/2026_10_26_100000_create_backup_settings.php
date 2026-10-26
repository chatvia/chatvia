<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\BackupSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('backup', function (SettingsBlueprint $group): void {
            $group->add('enabled');
            $group->add('frequency');
            $group->add('keep');
            $group->add('public_key');
            $group->add('include_files');
            $group->add('include_tool_databases');
            $group->add('pg_dump_path');
            $group->add('pg_restore_path');
            $group->add('redis_cli_path');
            $group->add('gpg_path');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'backup')->delete();
    }
};
