<?php

namespace Domain\Security\Data;

use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A workspace's {@see WorkspaceSecurityPolicy} as the Security settings page
 * shows it. The page saves through SecurityPolicyFormData instead, which holds
 * only what the form may change.
 *
 * `webhook_secret` is never populated, so the encrypted secret is never
 * serialized back to the client.
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
        public IpAllowlistMode $ip_allowlist_mode = IpAllowlistMode::Off,
        /** @var array<int, string> */
        public array $ip_allowlist = [],
        public GeoMode $geo_mode = GeoMode::Off,
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
            ip_allowlist: $policy->ip_allowlist ?? [],
            geo_mode: $policy->geo_mode,
            geo_countries: $policy->geo_countries ?? [],
            webhook_url: $policy->webhook_url,
            webhook_enabled: $policy->is_webhook_enabled,
            webhook_events: $policy->webhook_events,
            scim_enabled: $policy->is_scim_enabled,
        );
    }
}
