<?php

use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Http\Request;

/**
 * `$request->country()`, the visitor's country: Cloudflare's CF-IPCountry
 * header when the peer is Cloudflare or a trusted proxy, otherwise the
 * geolocated IP of the real visitor (CF-Connecting-IP), never of the edge.
 * Cloudflare's "no country" values fall back to the geolocation.
 */
it('believes the country header from a Cloudflare edge or a trusted proxy', function (): void {
    [$proxies, $headers] = [Request::getTrustedProxies(), Request::getTrustedHeaderSet()];
    Request::setTrustedProxies(['10.0.0.5'], Request::HEADER_X_FORWARDED_FOR);

    try {
        $fromEdge = Request::create('https://chatvia.test/', 'GET', server: ['HTTP_CF_IPCOUNTRY' => 'IQ', 'REMOTE_ADDR' => '172.70.1.1']);
        $fromProxy = Request::create('https://chatvia.test/', 'GET', server: ['HTTP_CF_IPCOUNTRY' => 'NL', 'REMOTE_ADDR' => '10.0.0.5']);

        expect($fromEdge->country())->toBe('iq')
            ->and($fromProxy->country())->toBe('nl');
    } finally {
        Request::setTrustedProxies($proxies, $headers);
    }
});

it('geolocates the real visitor, never the edge, when the header says nothing or is spoofed', function (): void {
    $guard = Mockery::mock(NetworkAccessGuard::class);
    $guard->shouldReceive('countryFor')->once()->with('198.51.100.7')->andReturn('DE');
    $guard->shouldReceive('countryFor')->once()->with('203.0.113.9')->andReturn('FR');
    app()->instance(NetworkAccessGuard::class, $guard);
    $sentinel = Request::create('https://chatvia.test/', 'GET', server: [
        'HTTP_CF_IPCOUNTRY' => 'XX',
        'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
        'REMOTE_ADDR' => '172.70.1.1',
    ]);
    $spoofed = Request::create('https://chatvia.test/', 'GET', server: [
        'HTTP_CF_IPCOUNTRY' => 'US',
        'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
        'REMOTE_ADDR' => '203.0.113.9',
    ]);

    expect($sentinel->country())->toBe('de')
        ->and($spoofed->country())->toBe('fr');
});
