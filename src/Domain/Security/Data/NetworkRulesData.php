<?php

namespace Domain\Security\Data;

use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * A workspace's network-access rules as the request gate reads them: the IP
 * allowlist (enforced only while its mode is on) and the country rule. Cached
 * as a plain array ({@see self::toCache()}) because the gate runs on every web
 * request.
 */
final readonly class NetworkRulesData
{
    /**
     * @param  list<string>  $ipAllowlist
     * @param  list<string>  $geoCountries
     */
    public function __construct(
        public IpAllowlistMode $ipAllowlistMode,
        public array $ipAllowlist,
        public GeoMode $geoMode,
        public array $geoCountries,
    ) {}

    public static function fromPolicy(WorkspaceSecurityPolicy $policy): self
    {
        return new self(
            ipAllowlistMode: $policy->ip_allowlist_mode,
            ipAllowlist: array_values($policy->ip_allowlist ?? []),
            geoMode: $policy->geo_mode,
            geoCountries: array_values($policy->geo_countries ?? []),
        );
    }

    /**
     * @param  array{ip_allowlist_mode: string, ip_allowlist: array<int, string>, geo_mode: string, geo_countries: array<int, string>}  $cached
     */
    public static function fromCache(array $cached): self
    {
        return new self(
            ipAllowlistMode: IpAllowlistMode::from($cached['ip_allowlist_mode']),
            ipAllowlist: array_values($cached['ip_allowlist']),
            geoMode: GeoMode::from($cached['geo_mode']),
            geoCountries: array_values($cached['geo_countries']),
        );
    }

    /**
     * @return array{ip_allowlist_mode: string, ip_allowlist: list<string>, geo_mode: string, geo_countries: list<string>}
     */
    public function toCache(): array
    {
        return [
            'ip_allowlist_mode' => $this->ipAllowlistMode->value,
            'ip_allowlist' => $this->ipAllowlist,
            'geo_mode' => $this->geoMode->value,
            'geo_countries' => $this->geoCountries,
        ];
    }

    /** Whether any rule is on (otherwise there is nothing to enforce). */
    public function isActive(): bool
    {
        return $this->ipAllowlistMode !== IpAllowlistMode::Off || $this->geoMode !== GeoMode::Off;
    }

    /**
     * The allowlist the gate applies: empty (no IP restriction) while the
     * allowlist is switched off.
     *
     * @return list<string>
     */
    public function enforcedIpAllowlist(): array
    {
        return $this->ipAllowlistMode === IpAllowlistMode::Off ? [] : $this->ipAllowlist;
    }
}
