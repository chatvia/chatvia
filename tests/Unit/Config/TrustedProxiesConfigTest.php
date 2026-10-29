<?php

declare(strict_types=1);

use Support\Http\CloudflareIps;
use Tests\Support\Config\ConfigFile;

/**
 * The trusted proxies come from config/trustedproxy.php, read when the config
 * loads (after .env). The file is re-evaluated with the given environment,
 * which is put back as it was afterwards.
 */
function tpcProxies(string $proxies, string $env): mixed
{
    return ConfigFile::with('trustedproxy.php', ['TRUSTED_PROXIES' => $proxies, 'APP_ENV' => $env])['proxies'];
}

it('reads the trusted proxies as a list, expanding the cloudflare keyword in any case to the published edge ranges', function (): void {
    expect(tpcProxies('Cloudflare, 10.0.0.1', 'production'))
        ->toBe([...CloudflareIps::ranges(), '10.0.0.1']);
});

it('trusts every proxy only in local', function (): void {
    expect(tpcProxies('*', 'production'))->toBeNull()
        ->and(tpcProxies('', 'production'))->toBeNull()
        ->and(tpcProxies('', 'local'))->toBe('*');
});
