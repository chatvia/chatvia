<?php

declare(strict_types=1);

namespace Support\Http;

use Illuminate\Support\Str;

/**
 * Turns the TRUSTED_PROXIES setting into the list the TrustProxies middleware
 * reads: comma-separated IPs or CIDRs, where the entry `cloudflare` stands for
 * Cloudflare's published edge ranges ({@see CloudflareIps}). Trusting every
 * proxy ('*') lets anyone fake their IP address, so it is only allowed in the
 * local environment; elsewhere an empty setting or '*' trusts none.
 */
final class TrustedProxies
{
    /**
     * @return '*'|list<string>|null
     */
    public static function parse(?string $setting, ?string $environment): string|array|null
    {
        $proxies = Str::trim($setting ?? '');

        if ($proxies === '*' || blank($proxies)) {
            return $environment === 'local' ? '*' : null;
        }

        return Str::of($proxies)
            ->explode(',')
            ->map(static fn (string $entry): string => Str::trim($entry))
            ->filter()
            ->flatMap(static fn (string $entry): array => Str::lower($entry) === 'cloudflare' ? CloudflareIps::ranges() : [$entry])
            ->unique()
            ->values()
            ->all();
    }
}
