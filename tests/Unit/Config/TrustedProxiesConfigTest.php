<?php

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * The trusted proxies come from config/trustedproxy.php, read when the config
 * loads (after .env), and the TrustProxies middleware applies them to each
 * request.
 */
function trustedProxiesWith(string $proxies, string $env): mixed
{
    $old = [$_SERVER['TRUSTED_PROXIES'] ?? null, $_SERVER['APP_ENV'] ?? null];
    $_SERVER['TRUSTED_PROXIES'] = $_ENV['TRUSTED_PROXIES'] = $proxies;
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $env;

    try {
        return (require base_path('config/trustedproxy.php'))['proxies'];
    } finally {
        [$_SERVER['TRUSTED_PROXIES'], $_SERVER['APP_ENV']] = $old;
        [$_ENV['TRUSTED_PROXIES'], $_ENV['APP_ENV']] = $old;
    }
}

it('reads the trusted proxies as a list', function (): void {
    expect(trustedProxiesWith('173.245.48.0/20, 10.0.0.1', 'production'))->toBe(['173.245.48.0/20', '10.0.0.1']);
});

it('trusts every proxy only in local', function (): void {
    expect(trustedProxiesWith('*', 'production'))->toBeNull()
        ->and(trustedProxiesWith('', 'production'))->toBeNull()
        ->and(trustedProxiesWith('', 'local'))->toBe('*');
});

it('takes the client IP from a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => ['10.0.0.1']]);

    $request = Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']);
    (new TrustProxies)->handle($request, fn (): ResponseFactory|\Illuminate\Http\Response => response('ok'));

    expect($request->ip())->toBe('203.0.113.7');
});
