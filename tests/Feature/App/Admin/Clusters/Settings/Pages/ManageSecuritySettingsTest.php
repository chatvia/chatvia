<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Admin\Settings\SecuritySettings;
use App\Http\Auth\BotCheck;
use App\Http\Auth\BotCheckForm;
use Filament\Facades\Filament;

/**
 * Settings → Security: the Cloudflare Turnstile keys and the forms they
 * protect, the guardrails, the tool-call log and rate limit, the security
 * headers, and the security audit log with how long it is kept.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    Filament::setCurrentPanel('admin');
});

it('turns the bot check on for the forms switched on', function (): void {
    adminPage(ManageSecuritySettings::class, $this->admin)
        ->assertSchemaStateSet(['turnstile_sign_up' => true, 'turnstile_sign_in' => false, 'turnstile_password_reset' => true])
        ->fillForm([
            'turnstile_site_key' => '0x4AAAAAAA-site',
            'turnstile_secret_key' => '0x4AAAAAAA-secret',
            'turnstile_sign_in' => true,
            'turnstile_password_reset' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    app()->forgetScopedInstances();

    expect(app(SecuritySettings::class)->turnstile_secret_key)->toBe('0x4AAAAAAA-secret')
        ->and(config('services.turnstile.key'))->toBe('0x4AAAAAAA-site')
        ->and(app(BotCheck::class)->protects(BotCheckForm::SignIn))->toBeTrue()
        ->and(app(BotCheck::class)->protects(BotCheckForm::PasswordReset))->toBeFalse()
        ->and(app(BotCheck::class)->forPages())->toBe(['site_key' => '0x4AAAAAAA-site', 'forms' => ['sign_up', 'sign_in']]);
});

it('saves the security headers and the audit log switch', function (): void {
    adminPage(ManageSecuritySettings::class, $this->admin)
        ->fillForm(['csp_mode' => 'enforce', 'security_audit_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.security.csp'))->toBe('enforce')
        ->and(config('chatvia.security_audit.enabled'))->toBeFalse();
});

it('keeps the security headers off the Platform page', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(ManagePlatformSettings::getUrl())
        ->assertOk()
        ->assertDontSee('Content Security Policy');
});

it('holds the guardrails, the tool-call limits and every log\'s retention', function (): void {
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

it('keeps the tool rounds per answer between 1 and 20, and a result limit of at least 1,000 characters', function (int $rounds, int $chars, array $errors): void {
    adminPage(ManageSecuritySettings::class, $this->admin)
        ->fillForm(['tool_max_rounds' => $rounds, 'tool_result_max_chars' => $chars])
        ->call('save')
        ->assertHasFormErrors($errors);
})->with([
    'no rounds' => [0, 64000, ['tool_max_rounds']],
    'too many rounds' => [21, 64000, ['tool_max_rounds']],
    'tiny results' => [5, 999, ['tool_result_max_chars']],
]);

it('refuses a security log floor above the days to keep it', function (): void {
    adminPage(ManageSecuritySettings::class, $this->admin)
        ->fillForm(['security_audit_retention_days' => 30, 'security_audit_retention_floor_days' => 60])
        ->call('save')
        ->assertHasFormErrors(['security_audit_retention_floor_days']);
});
