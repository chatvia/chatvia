<?php

use App\Admin\Clusters\Settings\Pages\ManageSecuritySettings;
use Filament\Facades\Filament;

/**
 * Settings → Security: the guardrails, tool approval, the tool-call limits
 * and every log's retention are saved into the config the app reads, and a
 * security log floor above its retention is refused.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
});

it('saves the guardrails, tool approval, the tool-call limits and every log\'s retention', function (): void {
    adminPage(ManageSecuritySettings::class, $this->admin)
        ->fillForm([
            'guardrails_enabled' => false,
            'guardrail_retention_days' => 30,
            'tool_audit_retention_days' => 60,
            'tool_rate_limit' => 5,
            'tool_rate_window_seconds' => 120,
            'tool_max_rounds' => 8,
            'tool_result_max_chars' => 20000,
            'tool_approval_enabled' => false,
            'tool_approval_ttl_minutes' => 30,
            'security_audit_retention_days' => 400,
            'security_audit_retention_floor_days' => 90,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.guardrails.enabled'))->toBeFalse()
        ->and(config('chatvia.guardrails.retention_days'))->toBe(30)
        ->and(config('chatvia.governance.retention_days'))->toBe(60)
        ->and(config('chatvia.governance.tool_rate_limit'))->toBe(5)
        ->and(config('chatvia.governance.tool_rate_window_seconds'))->toBe(120)
        ->and(config('chatvia.agent.tools.max_iterations'))->toBe(8)
        ->and(config('chatvia.agent.tools.max_result_chars'))->toBe(20000)
        ->and(config('chatvia.agent.tools.approval_enabled'))->toBeFalse()
        ->and(config('chatvia.agent.tools.approval_ttl_minutes'))->toBe(30)
        ->and(config('chatvia.security_audit.retention_days'))->toBe(400)
        ->and(config('chatvia.security_audit.retention_floor_days'))->toBe(90);
});

it('refuses a security log floor above the days to keep it', function (): void {
    adminPage(ManageSecuritySettings::class, $this->admin)
        ->fillForm(['security_audit_retention_days' => 30, 'security_audit_retention_floor_days' => 60])
        ->call('save')
        ->assertHasFormErrors(['security_audit_retention_floor_days']);
});
