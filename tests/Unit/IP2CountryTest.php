<?php

use Domain\Analytics\Services\IP2Country;
use Illuminate\Support\Facades\Http;

/**
 * Private, reserved and malformed addresses are never sent to the geolocation
 * provider and resolve to no country.
 */
beforeEach(function (): void {
    Http::fake();
});

it('does not look up local, private or invalid addresses', function (string $ip): void {
    expect(app(IP2Country::class)->countryCode($ip))->toBeNull();

    Http::assertNothingSent();
})->with([
    'localhost' => '127.0.0.1',
    'private class C' => '192.168.1.1',
    'private class A' => '10.0.0.1',
    'private class B' => '172.16.0.1',
    'link-local' => '169.254.1.1',
    'not an ip' => 'not-an-ip',
    'out of range' => '999.999.999.999',
    'empty' => '',
    'hostname' => 'localhost',
]);
