<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Clusters\Settings\Pages\ManageSecuritySettings;
use App\Admin\Settings\SecuritySettings;
use App\Http\Auth\BotCheck;
use App\Http\Auth\BotCheckForm;
use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Settings → Security: the Cloudflare Turnstile keys and the forms they
 * protect, the security headers (moved here from Platform), and the security
 * audit log switch.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    Filament::setCurrentPanel('admin');
});

it('turns the bot check on for the forms switched on', function (): void {
    Livewire::actingAs($this->admin, 'admin')->test(ManageSecuritySettings::class)
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

it('holds the security headers and the audit log switch', function (): void {
    Livewire::actingAs($this->admin, 'admin')->test(ManageSecuritySettings::class)
        ->fillForm(['csp_mode' => 'enforce', 'security_audit_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(config('chatvia.security.csp'))->toBe('enforce')
        ->and(config('chatvia.security_audit.enabled'))->toBeFalse();

    $this->actingAs($this->admin, 'admin')
        ->get(ManagePlatformSettings::getUrl())
        ->assertOk()
        ->assertDontSee('Content Security Policy');
});
