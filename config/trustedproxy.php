<?php

declare(strict_types=1);
use Support\Http\TrustedProxies;

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| The proxies in front of the app (a load balancer, Cloudflare) whose
| X-Forwarded-* headers are believed: TRUSTED_PROXIES, a comma-separated list
| of IPs or CIDRs, parsed by Support\Http\TrustedProxies. The entry
| `cloudflare` expands to Cloudflare's published edge ranges, so an install behind Cloudflare
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

return [

    'proxies' => TrustedProxies::parse(env('TRUSTED_PROXIES'), env('APP_ENV')),

];
