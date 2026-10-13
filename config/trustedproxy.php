<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| The proxies in front of the app (a load balancer, Cloudflare) whose
| X-Forwarded-* headers are believed: TRUSTED_PROXIES, a comma-separated list
| of IPs or CIDRs. The TrustProxies middleware reads `proxies` on every
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

return [

    'proxies' => match (true) {
        $proxies === '*', $proxies === '' => env('APP_ENV') === 'local' ? '*' : null,
        default => array_values(array_filter(array_map(trim(...), explode(',', $proxies)))),
    },

];
