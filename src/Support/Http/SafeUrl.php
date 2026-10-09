<?php

namespace Support\Http;

use Closure;
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
 *
 * A caller that sends the request itself should use pinnedClientOptions(): it
 * runs the same checks and also makes the HTTP client connect to the
 * addresses that were checked, instead of looking the host up a second time
 * (a DNS answer that changes in between could point at an internal address).
 */
final class SafeUrl
{
    /**
     * Replacement for DNS lookups, set by tests only.
     *
     * @var (Closure(string): list<string>)|null
     */
    private static ?Closure $resolver = null;

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
     * Checks the URL like {@see assert()} and returns the client options to
     * send it with: redirects off and, when the host is a name, the name
     * pinned to the addresses that were just checked (curl's CURLOPT_RESOLVE).
     * The URL keeps its host name, so TLS certificate checks and SNI work as
     * usual. In local and testing, where the address check is skipped,
     * nothing is pinned.
     *
     * @return array<string, mixed>
     *
     * @throws UnsafeUrlException
     */
    public static function pinnedClientOptions(string $url): array
    {
        $addresses = self::checkedAddresses($url);
        $options = self::clientOptions();
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if ($addresses === [] || filter_var($host, FILTER_VALIDATE_IP)) {
            return $options;
        }

        $options['curl'] = [CURLOPT_RESOLVE => [self::resolveEntry($url, $host, $addresses)]];

        return $options;
    }

    /**
     * Throw if the URL is unsafe to fetch server-side.
     *
     * @throws UnsafeUrlException
     */
    public static function assert(string $url): void
    {
        self::checkedAddresses($url);
    }

    /**
     * Runs every check and returns the addresses the host resolved to, all of
     * them public. Empty in local and testing, where the address check is
     * skipped.
     *
     * @return list<string>
     *
     * @throws UnsafeUrlException
     */
    private static function checkedAddresses(string $url): array
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
            return [];
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

        return $ips;
    }

    /**
     * One CURLOPT_RESOLVE entry, "host:port:address[,address…]". IPv4
     * addresses come first so a server without IPv6 connects at once; IPv6
     * addresses are written in brackets, as curl expects.
     *
     * @param  list<string>  $addresses
     */
    private static function resolveEntry(string $url, string $host, array $addresses): string
    {
        $port = parse_url($url, PHP_URL_PORT)
            ?? (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);

        usort($addresses, static fn (string $a, string $b): int => str_contains($a, ':') <=> str_contains($b, ':'));

        $written = array_map(static fn (string $ip): string => str_contains($ip, ':') ? '['.$ip.']' : $ip, $addresses);

        return $host.':'.$port.':'.implode(',', $written);
    }

    /**
     * Answer DNS lookups with the given callback instead of the network, or
     * restore real lookups with null. For tests.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveHostsUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
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
     * The IP addresses a host resolves to (the host itself for an IP literal),
     * or an empty list when it does not resolve.
     *
     * @return list<string>
     */
    public static function resolve(string $host): array
    {
        // parse_url() keeps the brackets around an IPv6 literal ("[::1]").
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        if (self::$resolver instanceof Closure) {
            return (self::$resolver)($host);
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
