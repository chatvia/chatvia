<?php

namespace Support\Http;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Cloudflare's published edge IP ranges (https://www.cloudflare.com/ips/),
 * vendored because they change rarely and the app must know them without a
 * network call: `TRUSTED_PROXIES=cloudflare` expands to them
 * (config/trustedproxy.php), and the `Request::country()` macro only believes
 * the `CF-*` headers on a connection that demonstrably came through Cloudflare
 * or a trusted proxy. To update, replace the two lists with the files at
 * cloudflare.com/ips-v4 and /ips-v6 (snapshot: 2026-10-16).
 */
final class CloudflareIps
{
    private const array IPV4 = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
    ];

    private const array IPV6 = [
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * Every Cloudflare range, v4 and v6.
     *
     * @return array<int, string>
     */
    public static function ranges(): array
    {
        return [...self::IPV4, ...self::IPV6];
    }

    /**
     * Whether an IP belongs to Cloudflare's edge.
     */
    public static function contains(string $ip): bool
    {
        return $ip !== '' && IpUtils::checkIp($ip, self::ranges());
    }
}
