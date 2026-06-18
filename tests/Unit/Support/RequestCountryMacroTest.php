<?php

use Illuminate\Http\Request;

function requestWithCountryHeader(?string $cfCountry): Request
{
    $server = $cfCountry !== null ? ['HTTP_CF_IPCOUNTRY' => $cfCountry] : [];

    // Default REMOTE_ADDR is 127.0.0.1 — a private IP, so the geo-IP fallback
    // resolves to null without any outbound lookup.
    return Request::create('https://chatvia.test/', 'GET', server: $server);
}

it('returns the lowercased Cloudflare visitor country', function (): void {
    expect(requestWithCountryHeader('IQ')->country())->toBe('iq');
});

it('ignores Cloudflare non-country sentinels and falls back', function (string $sentinel): void {
    // No usable header → geo-IP fallback on a private IP → null.
    expect(requestWithCountryHeader($sentinel)->country())->toBeNull();
})->with(['XX', 'T1']);

it('returns null when there is no country header and the IP is unresolvable', function (): void {
    expect(requestWithCountryHeader(null)->country())->toBeNull();
});
