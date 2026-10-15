<?php

declare(strict_types=1);

use Support\Http\SafeUrl;

/**
 * SafeUrl::isPublicIp() tells a public address from a private, loopback,
 * link-local or other reserved one.
 */
it('classifies private and reserved addresses as not public', function (string $ip): void {
    expect(SafeUrl::isPublicIp($ip))->toBeFalse();
})->with(['10.0.0.1', '172.16.0.1', '192.168.1.1', '127.0.0.1', '169.254.1.1', '::1', 'fc00::1', 'fe80::1']);

it('classifies public addresses as public', function (string $ip): void {
    expect(SafeUrl::isPublicIp($ip))->toBeTrue();
})->with(['1.1.1.1', '8.8.8.8', '142.250.74.46', '2606:4700:4700::1111']);
