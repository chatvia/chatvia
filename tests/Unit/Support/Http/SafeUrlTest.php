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

it('pins a host to the public addresses it checked, and refuses an internal one even in local unless an admin allowed it', function (): void {
    SafeUrl::resolveHostsUsing(fn (string $host): array => $host === 'hooks.example.com'
        ? ['2606:4700:4700::1111', '93.184.216.34']
        : ['93.184.216.34', '169.254.169.254']);
    $refusal = fn (Closure $call): ?Throwable => rescue(function () use ($call): null {
        $call();

        return null;
    }, fn (Throwable $e): Throwable => $e, report: false);

    $pinned = SafeUrl::pinnedClientOptions('https://hooks.example.com/in');
    $defaultPort = SafeUrl::pinnedClientOptions('http://hooks.example.com/in')['curl'][CURLOPT_RESOLVE];
    $literal = SafeUrl::pinnedClientOptions('https://93.184.216.34/in');
    $rebound = $refusal(fn () => SafeUrl::pinnedClientOptions('https://rebound.example.com/in'));
    app()['env'] = 'local';
    $loopbackInLocal = $refusal(fn () => SafeUrl::assert('http://127.0.0.1:8080/mcp'));
    config(['chatvia.security.allow_private_addresses' => true]);
    $allowed = $refusal(fn () => SafeUrl::assert('http://127.0.0.1:8080/mcp'));

    expect($pinned)->toBe([
        'allow_redirects' => false,
        'curl' => [CURLOPT_RESOLVE => ['hooks.example.com:443:93.184.216.34,[2606:4700:4700::1111]']],
    ])
        ->and($defaultPort)->toBe(['hooks.example.com:80:93.184.216.34,[2606:4700:4700::1111]'])
        ->and($literal)->toBe(['allow_redirects' => false])
        ->and($rebound)->toBeInstanceOf(UnsafeUrlException::class)
        ->and($loopbackInLocal)->toBeInstanceOf(UnsafeUrlException::class)
        ->and($allowed)->toBeNull()
        ->and(SafeUrl::pinnedClientOptions('http://127.0.0.1:8080/mcp'))->toBe(['allow_redirects' => false]);
});

it('keeps a stored visitor URL only with an http(s) scheme, cut to the stored length', function (?string $url, ?string $kept): void {
    expect(SafeUrl::httpSchemeOrNull($url))->toBe($kept);
})->with([
    'https' => ['https://example.com/pricing?plan=pro', 'https://example.com/pricing?plan=pro'],
    'javascript' => ['javascript:alert(1)', null],
    'blank' => ['', null],
    'too long' => ['https://example.com/'.Str::repeat('a', 3000), 'https://example.com/'.Str::repeat('a', SafeUrl::STORED_URL_LENGTH - 20)],
]);
