<?php

declare(strict_types=1);
use Support\Http\CloudflareIps;

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| The proxies in front of the app (a load balancer, Cloudflare) whose
| X-Forwarded-* headers are believed: TRUSTED_PROXIES, a comma-separated list
| of IPs or CIDRs. The entry `cloudflare` expands to Cloudflare's published
| edge ranges (Support\Http\CloudflareIps), so an install behind Cloudflare
| sets `TRUSTED_PROXIES=cloudflare` — plus its own proxy's IP when one sits
| in between, e.g. `cloudflare,172.16.0.0/12` for the Docker stack. The
| TrustProxies middleware reads `proxies` on every
| request. Trusting every proxy ('*') lets anyone fake their IP address,
| which defeats the IP rate limits and the widget's IP fingerprint, so it is
| only allowed when APP_ENV is local; elsewhere empty or '*' trusts none (the
| IP of the connection is used).
|
| Read here and not in bootstrap/app.php: the HTTP kernel is set up before
| .env is loaded.
|
*/

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

$expand = fn (string $entry): array => strcasecmp($entry, 'cloudflare') === 0
    ? CloudflareIps::ranges()
    : [$entry];

return [

    'proxies' => match (true) {
        $proxies === '*', $proxies === '' => env('APP_ENV') === 'local' ? '*' : null,
        default => array_values(array_unique(array_merge(
            ...array_map($expand, array_filter(array_map(trim(...), explode(',', $proxies)))),
        ))),
    },

];
