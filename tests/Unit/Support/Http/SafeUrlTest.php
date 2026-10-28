<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;

/**
 * SafeUrl tells a public address from a private, loopback, link-local or
 * other reserved one, and checks the addresses a host resolves to. The
 * request must then connect to exactly those addresses: if the HTTP client
 * looked the name up again, a DNS answer that changes between the check and
 * the request (DNS rebinding) could send it to an internal address.
 */
beforeEach(function (): void {
    app()['env'] = 'production';
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

it('classifies private and reserved addresses as not public', function (string $ip): void {
    expect(SafeUrl::isPublicIp($ip))->toBeFalse();
})->with(['10.0.0.1', '172.16.0.1', '192.168.1.1', '127.0.0.1', '169.254.1.1', '::1', 'fc00::1', 'fe80::1']);

it('classifies public addresses as public', function (string $ip): void {
    expect(SafeUrl::isPublicIp($ip))->toBeTrue();
})->with(['1.1.1.1', '8.8.8.8', '142.250.74.46', '2606:4700:4700::1111']);

it('pins the host to the addresses it checked', function (): void {
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['2606:4700:4700::1111', '93.184.216.34']);

    expect(SafeUrl::pinnedClientOptions('https://hooks.example.com/in'))->toBe([
        'allow_redirects' => false,
        'curl' => [CURLOPT_RESOLVE => ['hooks.example.com:443:93.184.216.34,[2606:4700:4700::1111]']],
    ]);
});

it('uses the port of the URL, or the default port of its scheme', function (string $url, string $entry): void {
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['93.184.216.34']);

    expect(SafeUrl::pinnedClientOptions($url)['curl'][CURLOPT_RESOLVE])->toBe([$entry]);
})->with([
    'explicit port' => ['https://hooks.example.com:8443/in', 'hooks.example.com:8443:93.184.216.34'],
    'http default' => ['http://hooks.example.com/in', 'hooks.example.com:80:93.184.216.34'],
]);

it('does not pin an IP literal, which is never looked up', function (): void {
    expect(SafeUrl::pinnedClientOptions('https://93.184.216.34/in'))->toBe(['allow_redirects' => false]);
});

it('refuses a host that resolves to an internal address', function (): void {
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['93.184.216.34', '169.254.169.254']);

    SafeUrl::pinnedClientOptions('https://hooks.example.com/in');
})->throws(UnsafeUrlException::class);

it('refuses an internal address when the install runs with APP_ENV=local', function (): void {
    app()['env'] = 'local';

    SafeUrl::assert('http://127.0.0.1:8080/mcp');
})->throws(UnsafeUrlException::class);

it('allows private addresses only when an admin allowed them', function (): void {
    app()['env'] = 'local';
    config(['chatvia.security.allow_private_addresses' => true]);

    SafeUrl::assert('http://127.0.0.1:8080/mcp');

    expect(SafeUrl::pinnedClientOptions('http://127.0.0.1:8080/mcp'))->toBe(['allow_redirects' => false]);
});

it('keeps a stored visitor URL only with an http(s) scheme, cut to the stored length', function (?string $url, ?string $kept): void {
    expect(SafeUrl::httpSchemeOrNull($url))->toBe($kept);
})->with([
    'https' => ['https://example.com/pricing?plan=pro', 'https://example.com/pricing?plan=pro'],
    'javascript' => ['javascript:alert(1)', null],
    'data' => ['data:text/html,<script>alert(1)</script>', null],
    'blank' => ['', null],
    'too long' => ['https://example.com/'.Str::repeat('a', 3000), 'https://example.com/'.Str::repeat('a', SafeUrl::STORED_URL_LENGTH - 20)],
]);
