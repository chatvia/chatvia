<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\SecuritySettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * The install's protections: the bot check (Cloudflare Turnstile) of the
 * sign-up, sign-in and password reset forms, the security headers, and the
 * security audit log.
 */
class ManageSecuritySettings extends AdminSettingsPage
{
    private const array CSP_MODES = [
        'report-only' => 'Report only (browsers report, nothing is blocked)',
        'enforce' => 'Enforce',
        'off' => 'Off (no security headers)',
    ];

    /** The form switches (App\Http\Auth\BotCheckForm), by what they cover. */
    private const array TURNSTILE_FORMS = [
        'turnstile_sign_up' => 'sign-up',
        'turnstile_sign_in' => 'sign-in',
        'turnstile_password_reset' => 'password reset',
    ];

    #[\Override]
    protected static string $settings = SecuritySettings::class;

    #[\Override]
    protected static ?string $title = 'Security';

    #[\Override]
    protected static ?string $slug = 'security';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    #[\Override]
    protected static ?int $navigationSort = 14;

    public static function description(): string
    {
        return 'Bot check on the sign-in forms, security headers and the audit log.';
    }

    public static function overviewGroup(): string
    {
        return 'Sign-in and people';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('turnstile', 'Bot check', 'Cloudflare Turnstile stops bots on the public forms, mostly without a puzzle for people.', Heroicon::OutlinedShieldCheck, fn (): array => [
                $this->text('turnstile_site_key', 'Site key', required: true),
                $this->secret('turnstile_secret_key', 'Secret key'),
                $this->toggle('turnstile_sign_up', 'Check sign-up', help: 'Creating a workspace account.'),
                $this->toggle('turnstile_sign_in', 'Check sign-in', help: 'Signing in with a password, and asking for a sign-in link.'),
                $this->toggle('turnstile_password_reset', 'Check password reset', help: 'Asking for a password reset link.'),
            ])->required('turnstile_site_key', 'turnstile_secret_key')
                ->console('https://dash.cloudflare.com/?to=/:account/turnstile')
                ->summary(fn (): string => $this->turnstileSummary()),
            SettingsCard::make('headers', 'Security headers', 'The Content Security Policy of every page.', Heroicon::OutlinedLockClosed, fn (): array => [
                $this->choice('csp_mode', 'Content Security Policy', self::CSP_MODES,
                    help: 'Enforce also sends X-Frame-Options and, over HTTPS, HSTS.'),
                $this->url('csp_report_uri', 'Report address', help: 'Optional. Receives the reports of blocked content.'),
            ])->summary(fn (): string => self::CSP_MODES[(string) $this->valueInUse('csp_mode')] ?? $this->shown($this->valueInUse('csp_mode'))),
            SettingsCard::make('audit', 'Security audit log', 'A record of sign-ins, membership and configuration changes in every workspace.', Heroicon::OutlinedClipboardDocumentList, fn (): array => [
                $this->toggle('security_audit_enabled', 'Keep the security audit log',
                    help: 'Workspaces see their own log. How long it is kept is under Retention & limits.'),
            ])->summary(fn (): string => $this->valueInUse('security_audit_enabled') ? 'On' : 'Off'),
        ];
    }

    private function turnstileSummary(): string
    {
        $on = array_filter(self::TURNSTILE_FORMS, fn (string $property): bool => (bool) $this->valueInUse($property), ARRAY_FILTER_USE_KEY);

        return $on === [] ? 'On no form' : 'Checks '.implode(', ', $on);
    }
}
