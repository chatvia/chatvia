<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * Advanced limits: how long the tool-call audit, the guardrail log, the
 * security log and the AI call log are kept, the per-tool rate limit, and the
 * guardrails master switch.
 */
class LimitSettings extends AdminSettings
{
    #[ConfigKey('chatvia.governance.retention_days')]
    public ?int $tool_audit_retention_days = null;

    #[ConfigKey('chatvia.governance.tool_rate_limit')]
    public ?int $tool_rate_limit = null;

    #[ConfigKey('chatvia.governance.tool_rate_window_seconds')]
    public ?int $tool_rate_window_seconds = null;

    #[ConfigKey('chatvia.guardrails.enabled')]
    public ?bool $guardrails_enabled = null;

    #[ConfigKey('chatvia.guardrails.retention_days')]
    public ?int $guardrail_retention_days = null;

    #[ConfigKey('chatvia.security_audit.retention_days')]
    public ?int $security_audit_retention_days = null;

    #[ConfigKey('chatvia.security_audit.retention_floor_days')]
    public ?int $security_audit_retention_floor_days = null;

    #[ConfigKey('chatvia.spectra.retention_days')]
    public ?int $spectra_retention_days = null;

    public static function group(): string
    {
        return 'limits';
    }
}
