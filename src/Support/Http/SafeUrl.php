<?php

namespace Support\Http;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use League\Uri\Contracts\UriException;
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
 * The IP check is on whatever APP_ENV says. It is skipped only while the
 * test suite runs (which never looks up DNS), or when an admin allows private
 * addresses for local development (Settings → Security,
 * `chatvia.security.allow_private_addresses`).
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
     * usual. When the address check is skipped, nothing is pinned.
     *
     * @return array<string, mixed>
     *
     * @throws UnsafeUrlException
     */
    public static function pinnedClientOptions(string $url): array
    {
        $addresses = self::checkedAddresses($url);
        $options = self::clientOptions();
        $host = Str::trim((string) self::parse($url)?->host(), '[]');

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
     * them public. Empty when the address check is skipped.
     *
     * @return list<string>
     *
     * @throws UnsafeUrlException
     */
    private static function checkedAddresses(string $url): array
    {
        $uri = self::parse($url);
        $scheme = Str::lower((string) $uri?->scheme());
        $host = (string) $uri?->host();

        if ($scheme === '' || $host === '') {
            throw new UnsafeUrlException('The URL is malformed.');
        }

        if (! collect(['http', 'https'])->containsStrict($scheme)) {
            throw new UnsafeUrlException('Only http and https URLs are allowed.');
        }

        if (Str::contains($host, '..') || Str::isMatch('/[\s<>"\\\\]/', $host)) {
            throw new UnsafeUrlException('The URL host is malformed.');
        }

        if (self::skipsAddressCheck()) {
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
     * The suite fakes every HTTP call and must not look up DNS; a test that
     * checks addresses switches the environment away from `testing`. An
     * install never skips the check because of APP_ENV (a forgotten
     * APP_ENV=local would switch off the SSRF guard), only when an admin
     * allows private addresses.
     */
    private static function skipsAddressCheck(): bool
    {
        return app()->runningUnitTests() || config('chatvia.security.allow_private_addresses') === true;
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
        $uri = self::parse($url);
        $port = $uri?->port() ?? (Str::lower((string) $uri?->scheme()) === 'https' ? 443 : 80);

        usort($addresses, static fn (string $a, string $b): int => Str::contains($a, ':') <=> Str::contains($b, ':'));

        $written = array_map(static fn (string $ip): string => Str::contains($ip, ':') ? "[{$ip}]" : $ip, $addresses);

        return "{$host}:{$port}:".Arr::join($written, ',');
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

    /** The most of a visitor's page or referrer URL that is kept. */
    public const int STORED_URL_LENGTH = 2048;

    /**
     * Return the URL only if its scheme is http/https, else null, cut to
     * {@see STORED_URL_LENGTH}. Cheap guard for values that are merely STORED
     * and later rendered (a visitor's page_url/referrer_url shown in the
     * dashboard) so a `javascript:`/`data:` URI can never reach an href and
     * a visitor cannot fill the row with an endless one.
     */
    public static function httpSchemeOrNull(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $scheme = Str::lower((string) self::parse($url)?->scheme());

        return collect(['http', 'https'])->containsStrict($scheme) ? Str::limit($url, self::STORED_URL_LENGTH, '') : null;
    }

    /**
     * The URL as a Uri, or null when it is blank or malformed. A URL typed by
     * a person can be anything, and Uri::of() throws on a malformed one.
     */
    public static function parse(?string $url): ?Uri
    {
        if (blank($url)) {
            return null;
        }

        try {
            return Uri::of($url);
        } catch (UriException) {
            return null;
        }
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
        // A URL's host keeps the brackets around an IPv6 literal ("[::1]").
        $host = Str::trim($host, '[]');

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
