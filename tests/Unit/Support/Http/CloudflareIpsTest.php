<?php

declare(strict_types=1);

use Support\Http\CloudflareIps;

/**
 * CloudflareIps tells Cloudflare's published edge addresses, v4 and v6,
 * from every other address, so only a request from the edge is believed
 * when it names the visitor's IP and country.
 */
it('recognizes Cloudflare edge addresses, v4 and v6', function (): void {
    expect(CloudflareIps::contains('172.70.1.1'))->toBeTrue()
        ->and(CloudflareIps::contains('104.16.1.1'))->toBeTrue()
        ->and(CloudflareIps::contains('2606:4700::1'))->toBeTrue();
});

it('rejects every other address', function (): void {
    expect(CloudflareIps::contains('203.0.113.9'))->toBeFalse()
        ->and(CloudflareIps::contains('127.0.0.1'))->toBeFalse()
        ->and(CloudflareIps::contains('2001:db8::1'))->toBeFalse()
        ->and(CloudflareIps::contains(''))->toBeFalse()
        ->and(CloudflareIps::contains('not-an-ip'))->toBeFalse();
});
