<?php

declare(strict_types=1);
use Support\Http\CloudflareIps;

/**
 * The trusted proxies come from config/trustedproxy.php, read when the config
 * loads (after .env). The file is re-evaluated with the given environment,
 * which is put back as it was afterwards.
 */
function trustedProxiesWith(string $proxies, string $env): mixed
{
    $vars = ['TRUSTED_PROXIES' => $proxies, 'APP_ENV' => $env];
    $saved = [];

    foreach ($vars as $key => $value) {
        $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null];
        $_SERVER[$key] = $_ENV[$key] = $value;
    }

    try {
        return (require base_path('config/trustedproxy.php'))['proxies'];
    } finally {
        foreach ($saved as $key => [$server, $envValue]) {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }

            if ($envValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $envValue;
            }
        }
    }
}

it('reads the trusted proxies as a list', function (): void {
    expect(trustedProxiesWith('173.245.48.0/20, 10.0.0.1', 'production'))->toBe(['173.245.48.0/20', '10.0.0.1']);
});

it('expands the cloudflare keyword to the published edge ranges', function (): void {
    expect(trustedProxiesWith('cloudflare,10.0.0.1', 'production'))
        ->toBe([...CloudflareIps::ranges(), '10.0.0.1']);
});

it('matches the cloudflare keyword case-insensitively', function (): void {
    expect(trustedProxiesWith('Cloudflare', 'production'))
        ->toBe(CloudflareIps::ranges());
});

it('trusts every proxy only in local', function (): void {
    expect(trustedProxiesWith('*', 'production'))->toBeNull()
        ->and(trustedProxiesWith('', 'production'))->toBeNull()
        ->and(trustedProxiesWith('', 'local'))->toBe('*');
});
