<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\SecuritySettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('security', function (SettingsBlueprint $group): void {
            $group->add('turnstile_site_key');
            $group->addEncrypted('turnstile_secret_key');
            $group->add('turnstile_sign_up');
            $group->add('turnstile_sign_in');
            $group->add('turnstile_password_reset');
            $group->add('guardrails_enabled');
            $group->add('guardrail_retention_days');
            $group->add('max_message_chars');
            $group->add('tool_audit_retention_days');
            $group->add('tool_rate_limit');
            $group->add('tool_rate_window_seconds');
            $group->add('tool_max_rounds');
            $group->add('tool_result_max_chars');
            $group->add('tool_approval_enabled');
            $group->add('tool_approval_ttl_minutes');
            $group->add('csp_mode');
            $group->add('csp_report_uri');
            $group->add('allow_private_addresses');
            $group->add('security_audit_enabled');
            $group->add('security_audit_retention_days');
            $group->add('security_audit_retention_floor_days');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'security')->delete();
    }
};
