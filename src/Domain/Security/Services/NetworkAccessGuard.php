<?php

namespace Domain\Security\Services;

use Domain\Analytics\Services\IP2Country;
use Domain\Security\Enums\GeoMode;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Evaluates a workspace's network-access rules (IP allowlist + geo) against a
 * single request's IP/country. The decision in {@see self::passes()} is pure so
 * both the EnsureWorkspaceNetworkAllowed middleware (live request) and the
 * security-policy FormRequest's anti-lockout check (proposed rules) share one
 * source of truth. {@see self::countryFor()} resolves + caches the geo lookup.
 *
 * Fail-open on geo: an unresolved country (private IP, lookup outage) never
 * blocks — a geo-IP provider hiccup must not lock a workspace out of its own
 * dashboard. The IP allowlist is deterministic and fails closed.
 */
class NetworkAccessGuard
{
    public function __construct(
        private readonly IP2Country $geolocation,
    ) {}

    /**
     * Whether a request from $ip / $country satisfies BOTH the IP allowlist and
     * the geo rule. Composed from the two independent predicates below so the
     * anti-lockout check can tell which rule (IP or geo) is the cause and word
     * its message accordingly — a country-only rule must never blame the IP.
     *
     * @param  array<int, string>  $ipAllowlist  allowed IPs / CIDRs
     * @param  array<int, string>  $geoCountries  ISO-3166 alpha-2 codes
     */
    public function passes(array $ipAllowlist, GeoMode $geoMode, array $geoCountries, string $ip, ?string $country): bool
    {
        return $this->ipAllowed($ipAllowlist, $ip)
            && $this->geoAllowed($geoMode, $geoCountries, $country);
    }

    /**
     * IP-allowlist verdict in isolation. An empty allowlist imposes no IP
     * restriction; otherwise the IP must match an entry (fails closed).
     *
     * @param  array<int, string>  $ipAllowlist  allowed IPs / CIDRs
     */
    public function ipAllowed(array $ipAllowlist, string $ip): bool
    {
        return $ipAllowlist === [] || IpUtils::checkIp($ip, $ipAllowlist);
    }

    /**
     * Geo verdict in isolation. Off imposes no restriction, and an unresolved
     * country fails OPEN — a geo-IP outage must not lock a workspace out.
     * Country comparison is case-insensitive (the provider and the countries
     * table can differ in case).
     *
     * @param  array<int, string>  $geoCountries  ISO-3166 alpha-2 codes
     */
    public function geoAllowed(GeoMode $geoMode, array $geoCountries, ?string $country): bool
    {
        if ($country === null || $geoMode === GeoMode::Off) {
            return true;
        }

        $listed = in_array(strtolower($country), array_map(strtolower(...), $geoCountries), true);

        return $geoMode === GeoMode::Allow ? $listed : ! $listed;
    }

    /**
     * Country (ISO-3166 alpha-2) for an IP, cached for a day. Returns null when
     * unresolved (private/local IP or provider failure); callers treat null as
     * fail-open. Only real resolutions are cached so transient failures retry.
     */
    public function countryFor(string $ip): ?string
    {
        $key = "security:geoip:{$ip}";

        $cached = Cache::get($key);
        if ($cached !== null) {
            return filled($cached) ? $cached : null;
        }

        $country = $this->geolocation->countryCode($ip);

        if ($country !== null) {
            Cache::put($key, $country, now()->addDay());
        }

        return $country;
    }
}
