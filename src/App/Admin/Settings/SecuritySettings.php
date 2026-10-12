<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Http\Auth\BotCheck;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * The install's protections: the bot check of the public forms (Cloudflare
 * Turnstile, {@see BotCheck}) and the forms it covers, the security headers
 * (Content Security Policy), and the security audit log.
 */
class SecuritySettings extends AdminSettings
{
    #[ConfigKey('services.turnstile.key')]
    public ?string $turnstile_site_key = null;

    #[ConfigKey('services.turnstile.secret'), ShouldBeEncrypted]
    public ?string $turnstile_secret_key = null;

    #[ConfigKey('chatvia.security.turnstile.sign_up')]
    public ?bool $turnstile_sign_up = null;

    #[ConfigKey('chatvia.security.turnstile.sign_in')]
    public ?bool $turnstile_sign_in = null;

    #[ConfigKey('chatvia.security.turnstile.password_reset')]
    public ?bool $turnstile_password_reset = null;

    #[ConfigKey('chatvia.security.csp')]
    public ?string $csp_mode = null;

    #[ConfigKey('chatvia.security.csp_report_uri')]
    public ?string $csp_report_uri = null;

    #[ConfigKey('chatvia.security_audit.enabled')]
    public ?bool $security_audit_enabled = null;

    public static function group(): string
    {
        return 'security';
    }
}
