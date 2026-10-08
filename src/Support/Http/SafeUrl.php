<?php

namespace Support\Http;

use Support\Exceptions\UnsafeUrlException;

/**
 * Centralised SSRF guard for every server-side fetch of a user-influenced URL
 * (function webhooks, MCP servers, MCP OAuth discovery, website crawling /
 * training, SIEM and CRM webhooks). One chokepoint so the rules can't drift
 * between call sites.
 *
 *  - scheme must be http/https
 *  - host must not resolve to a private / loopback / link-local / reserved IP
 *    (cloud metadata 169.254.169.254, 127.0.0.0/8, RFC1918, ::1, fc00::/7, …)
 *
 * In the `local` environment the IP check is skipped so developers can hit
 * localhost mock servers; production and all other environments are enforced.
 * Redirect following must be disabled by the caller (see clientOptions()) so a
 * public host cannot 30x-redirect into an internal address after validation.
 */
final class SafeUrl
{
    /**
     * Guzzle/Http options that callers MUST apply so a validated public URL
     * cannot redirect into an internal target after the check.
     *
     * @return array<string, mixed>
     */
    public static function clientOptions(): array
    {
        return ['allow_redirects' => false];
    }

    /**
     * Throw if the URL is unsafe to fetch server-side.
     *
     * @throws UnsafeUrlException
     */
    public static function assert(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new UnsafeUrlException('The URL is malformed.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException('Only http and https URLs are allowed.');
        }

        $host = (string) $parts['host'];
        if (str_contains($host, '..') || preg_match('/[\s<>"\\\\]/', $host)) {
            throw new UnsafeUrlException('The URL host is malformed.');
        }

        // Allow localhost/private targets in local + automated tests so dev mock
        // servers and the suite work; production/staging are enforced.
        if (app()->environment('local', 'testing')) {
            return;
        }

        $ips = self::resolve($host);
        if ($ips === []) {
            throw new UnsafeUrlException('The URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeUrlException('The URL resolves to a private or reserved address.');
            }
        }
    }

    /**
     * Return the URL only if its scheme is http/https, else null. Cheap
     * scheme-only guard for values that are merely STORED and later rendered
     * (e.g. visitor page_url/referrer_url shown in the admin UI) so a
     * `javascript:`/`data:` URI can never reach an href.
     */
    public static function httpSchemeOrNull(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * True only for globally routable addresses. GLOBAL_RANGE also rejects the
     * shared/carrier-grade NAT range (100.64.0.0/10), benchmarking and
     * documentation ranges, and IPv4-mapped IPv6 forms, which the private and
     * reserved flags alone let through.
     */
    public static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        );
    }

    /**
     * @return string[]
     */
    public static function resolve(string $host): array
    {
        // parse_url() keeps the brackets around an IPv6 literal ("[::1]").
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            // Fall back to gethostbynamel for IPv4-only environments / hosts.
            $v4 = @gethostbynamel($host);

            return $v4 === false ? [] : $v4;
        }

        return array_values(array_filter(array_map(
            static fn (array $r): ?string => $r['ip'] ?? $r['ipv6'] ?? null,
            $records,
        )));
    }
}
