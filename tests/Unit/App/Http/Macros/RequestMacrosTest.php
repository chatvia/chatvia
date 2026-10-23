<?php

use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Http\Request;

function requestWithCountryHeader(?string $cfCountry, string $remoteAddr = '172.70.1.1'): Request
{
    // The default REMOTE_ADDR is a Cloudflare edge IP (172.64.0.0/13), so the
    // CF-* headers count as genuine.
    $server = ['REMOTE_ADDR' => $remoteAddr];

    if ($cfCountry !== null) {
        $server['HTTP_CF_IPCOUNTRY'] = $cfCountry;
    }

    return Request::create('https://chatvia.test/', 'GET', server: $server);
}

it('returns the lowercased Cloudflare visitor country', function (): void {
    expect(requestWithCountryHeader('IQ')->country())->toBe('iq');
});

it('ignores Cloudflare non-country sentinels and falls back to geolocation', function (string $sentinel): void {
    $guard = Mockery::mock(NetworkAccessGuard::class);
    $guard->shouldReceive('countryFor')->once()->with('172.70.1.1')->andReturnNull();
    app()->instance(NetworkAccessGuard::class, $guard);

    expect(requestWithCountryHeader($sentinel)->country())->toBeNull();
})->with(['XX', 'T1']);

it('returns null when there is no country header and the IP is unresolvable', function (): void {
    // A direct connection from a private IP: no CF headers to trust, and the
    // geo-IP fallback resolves a private IP to null without any lookup.
    expect(requestWithCountryHeader(null, '127.0.0.1')->country())->toBeNull();
});

it('geolocates the real visitor from CF-Connecting-IP, never the Cloudflare edge', function (): void {
    $guard = Mockery::mock(NetworkAccessGuard::class);
    $guard->shouldReceive('countryFor')->once()->with('198.51.100.7')->andReturn('DE');
    app()->instance(NetworkAccessGuard::class, $guard);

    $request = Request::create('https://chatvia.test/', 'GET', server: [
        'HTTP_CF_IPCOUNTRY' => 'XX',
        'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
        'REMOTE_ADDR' => '172.70.1.1',
    ]);

    expect($request->country())->toBe('de');
});

it('ignores CF headers from a peer that is neither Cloudflare nor a trusted proxy', function (): void {
    // A spoofer connecting straight to the origin: both CF headers are lies.
    // The peer's own IP is geolocated instead.
    $guard = Mockery::mock(NetworkAccessGuard::class);
    $guard->shouldReceive('countryFor')->once()->with('203.0.113.9')->andReturn('FR');
    app()->instance(NetworkAccessGuard::class, $guard);

    $request = Request::create('https://chatvia.test/', 'GET', server: [
        'HTTP_CF_IPCOUNTRY' => 'US',
        'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
        'REMOTE_ADDR' => '203.0.113.9',
    ]);

    expect($request->country())->toBe('fr');
});

it('believes CF headers forwarded through a trusted proxy', function (): void {
    [$proxies, $headers] = [Request::getTrustedProxies(), Request::getTrustedHeaderSet()];
    Request::setTrustedProxies(['10.0.0.5'], Request::HEADER_X_FORWARDED_FOR);

    try {
        $request = Request::create('https://chatvia.test/', 'GET', server: [
            'HTTP_CF_IPCOUNTRY' => 'NL',
            'REMOTE_ADDR' => '10.0.0.5',
        ]);

        expect($request->country())->toBe('nl');
    } finally {
        Request::setTrustedProxies($proxies, $headers);
    }
});
