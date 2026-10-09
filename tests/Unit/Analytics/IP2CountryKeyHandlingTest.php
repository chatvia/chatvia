<?php

use Domain\Analytics\Services\IP2Country;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

/**
 * The IP2Location API key must never travel in the URL (where it ends up in
 * transport error messages and reports), and a failed lookup must not report
 * anything that still carries it.
 */
beforeEach(function (): void {
    config([
        'services.ip2location.endpoint' => 'https://ip2location.test/',
        'services.ip2location.key' => 'super-secret-key',
    ]);
});

it('sends the API key as a bearer token, not in the query string', function (): void {
    Http::fake(['ip2location.test*' => Http::response(['country_code' => 'DE'])]);

    app(IP2Country::class)->locate('8.8.8.8');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer super-secret-key')
        && ! str_contains($request->url(), 'super-secret-key')
        && $request['ip'] === '8.8.8.8');
});

it('reports a failed lookup without the API key and returns no country', function (): void {
    Exceptions::fake();
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://ip2location.test/?key=super-secret-key&ip=8.8.8.8'));

    expect(app(IP2Country::class)->countryCode('8.8.8.8'))->toBeNull();

    Exceptions::assertReported(fn (RuntimeException $e): bool => ! str_contains($e->getMessage(), 'super-secret-key')
        && str_contains($e->getMessage(), '[redacted]')
        && $e->getPrevious() === null);
});
