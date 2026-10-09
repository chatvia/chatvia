<?php

namespace Domain\Security\Data;

use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Spatie\LaravelData\Data;

/**
 * What the Security settings form submits: the network rules (IP allowlist and
 * country rule), conversation and audit retention, and the SIEM webhook.
 * Everything else on the policy keeps its stored value; the webhook fields
 * apply only when the workspace's plan includes the SIEM webhook.
 *
 * `webhook_secret` is write-only: a value rotates the stored secret, null
 * leaves it untouched.
 */
class SecurityPolicyFormData extends Data
{
    /**
     * @param  list<string>  $ip_allowlist  IPs and CIDR ranges
     * @param  list<string>  $geo_countries  lower-case ISO-3166 alpha-2 codes
     */
    public function __construct(
        public ?int $retention_conversations_days = null,
        public ?int $retention_audit_days = null,
        public IpAllowlistMode $ip_allowlist_mode = IpAllowlistMode::Off,
        public array $ip_allowlist = [],
        public GeoMode $geo_mode = GeoMode::Off,
        public array $geo_countries = [],
        public ?string $webhook_url = null,
        public ?string $webhook_secret = null,
        public bool $webhook_enabled = false,
    ) {}

    public function rotatesWebhookSecret(): bool
    {
        return $this->webhook_secret !== null && $this->webhook_secret !== '';
    }
}
