<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\LimitSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('limits', function (SettingsBlueprint $group): void {
            $group->add('tool_audit_retention_days');
            $group->add('tool_rate_limit');
            $group->add('tool_rate_window_seconds');
            $group->add('guardrails_enabled');
            $group->add('guardrail_retention_days');
            $group->add('security_audit_retention_days');
            $group->add('security_audit_retention_floor_days');
            $group->add('spectra_retention_days');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'limits')->delete();
    }
};
