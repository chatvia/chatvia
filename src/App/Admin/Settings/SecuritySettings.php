<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Http\Auth\BotCheck;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * The install's protections and their logs: the bot check of the public
 * forms (Cloudflare Turnstile, {@see BotCheck}) and the forms it covers, the
 * guardrails master switch and the longest visitor message, the tool-call limits (rate, rounds per answer,
 * result size) and approval switch, the security headers
 * (Content Security Policy), whether outgoing requests may reach private
 * network addresses, the security audit log, and how long the guardrail,
 * tool-call and security logs are kept.
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

    #[ConfigKey('chatvia.guardrails.enabled')]
    public ?bool $guardrails_enabled = null;

    #[ConfigKey('chatvia.guardrails.retention_days')]
    public ?int $guardrail_retention_days = null;

    #[ConfigKey('chatvia.guardrails.max_message_chars')]
    public ?int $max_message_chars = null;

    #[ConfigKey('chatvia.governance.retention_days')]
    public ?int $tool_audit_retention_days = null;

    #[ConfigKey('chatvia.governance.tool_rate_limit')]
    public ?int $tool_rate_limit = null;

    #[ConfigKey('chatvia.governance.tool_rate_window_seconds')]
    public ?int $tool_rate_window_seconds = null;

    #[ConfigKey('chatvia.agent.tools.max_iterations')]
    public ?int $tool_max_rounds = null;

    #[ConfigKey('chatvia.agent.tools.max_result_chars')]
    public ?int $tool_result_max_chars = null;

    #[ConfigKey('chatvia.agent.tools.approval_enabled')]
    public ?bool $tool_approval_enabled = null;

    #[ConfigKey('chatvia.agent.tools.approval_ttl_minutes')]
    public ?int $tool_approval_ttl_minutes = null;

    #[ConfigKey('chatvia.security.csp')]
    public ?string $csp_mode = null;

    #[ConfigKey('chatvia.security.csp_report_uri')]
    public ?string $csp_report_uri = null;

    #[ConfigKey('chatvia.security.allow_private_addresses')]
    public ?bool $allow_private_addresses = null;

    #[ConfigKey('chatvia.security_audit.enabled')]
    public ?bool $security_audit_enabled = null;

    #[ConfigKey('chatvia.security_audit.retention_days')]
    public ?int $security_audit_retention_days = null;

    #[ConfigKey('chatvia.security_audit.retention_floor_days')]
    public ?int $security_audit_retention_floor_days = null;

    public static function group(): string
    {
        return 'security';
    }
}
