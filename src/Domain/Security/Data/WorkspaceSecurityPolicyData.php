<?php

namespace Domain\Security\Data;

use Domain\Security\Models\WorkspaceSecurityPolicy;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The writable shape of a workspace's {@see WorkspaceSecurityPolicy}. Built
 * from the security-policy FormRequest on the write path and from the model on
 * the read path.
 *
 * `webhook_secret` is write-only: {@see self::fromModel()} never populates it,
 * so the encrypted secret is never serialized back to the client. A non-null
 * value on the write path rotates it; null leaves the stored secret untouched.
 */
#[TypeScript]
class WorkspaceSecurityPolicyData extends Data
{
    public function __construct(
        public ?int $retention_conversations_days = null,
        public ?int $retention_leads_days = null,
        public ?int $retention_audit_days = null,
        public ?int $retention_analytics_days = null,
        public ?int $anonymize_after_days = null,
        public string $ip_allowlist_mode = 'off',
        public string $geo_mode = 'off',
        /** @var array<int, string> */
        public array $geo_countries = [],
        public ?string $webhook_url = null,
        public ?string $webhook_secret = null,
        public bool $webhook_enabled = false,
        /** @var array<int, string>|null */
        public ?array $webhook_events = null,
        public bool $scim_enabled = false,
    ) {}

    public static function fromModel(WorkspaceSecurityPolicy $policy): self
    {
        return new self(
            retention_conversations_days: $policy->retention_conversations_days,
            retention_leads_days: $policy->retention_leads_days,
            retention_audit_days: $policy->retention_audit_days,
            retention_analytics_days: $policy->retention_analytics_days,
            anonymize_after_days: $policy->anonymize_after_days,
            ip_allowlist_mode: $policy->ip_allowlist_mode,
            geo_mode: $policy->geo_mode,
            geo_countries: $policy->geo_countries ?? [],
            webhook_url: $policy->webhook_url,
            webhook_secret: null,
            webhook_enabled: $policy->webhook_enabled,
            webhook_events: $policy->webhook_events,
            scim_enabled: $policy->scim_enabled,
        );
    }
}
