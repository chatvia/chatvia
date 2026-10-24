<?php

use Domain\Analytics\Services\IP2Country;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * IP geolocation through IP2Location. Private, reserved and malformed
 * addresses are never sent to the provider. The API key must never travel in
 * the URL (where it ends up in transport error messages and reports), and a
 * failed lookup must not report anything that still carries it.
 */
beforeEach(function (): void {
    config([
        'services.ip2location.endpoint' => 'https://ip2location.test/',
        'services.ip2location.key' => 'super-secret-key',
    ]);
});

it('does not look up local, private or invalid addresses', function (string $ip): void {
    Http::fake();

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

it('looks nothing up while no key is set up', function (): void {
    Http::fake();
    config(['services.ip2location.key' => null]);

    expect(app(IP2Country::class)->countryCode('8.8.8.8'))->toBeNull();

    Http::assertNothingSent();
});

it('sends the API key as a bearer token, not in the query string', function (): void {
    Http::fake(['ip2location.test*' => Http::response(['country_code' => 'DE'])]);

    app(IP2Country::class)->locate('8.8.8.8');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer super-secret-key')
        && ! Str::contains($request->url(), 'super-secret-key')
        && $request['ip'] === '8.8.8.8');
});

it('reports a failed lookup without the API key and returns no country', function (): void {
    Exceptions::fake();
    Http::fake(fn (): never => throw new ConnectionException('cURL error 28 for https://ip2location.test/?key=super-secret-key&ip=8.8.8.8'));

    expect(app(IP2Country::class)->countryCode('8.8.8.8'))->toBeNull();

    Exceptions::assertReported(fn (RuntimeException $e): bool => ! Str::contains($e->getMessage(), 'super-secret-key')
        && Str::contains($e->getMessage(), '[redacted]')
        && ! $e->getPrevious() instanceof Throwable);
});
