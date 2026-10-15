<?php

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;
use Support\Http\SignedWebhookSender;

/**
 * SafeUrl checks the addresses a host resolves to, and the request must then
 * connect to exactly those addresses. If the HTTP client looked the name up
 * again, a DNS answer that changes between the check and the request (DNS
 * rebinding) could send it to an internal address.
 */
beforeEach(function (): void {
    app()['env'] = 'production';
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

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

it('sends a webhook to the address that was checked, even if DNS changes afterwards', function (): void {
    $answers = [['93.184.216.34'], ['10.0.0.5']];
    SafeUrl::resolveHostsUsing(function (string $host) use (&$answers): array {
        return array_shift($answers) ?? ['10.0.0.5'];
    });

    $pinned = null;
    Http::fake(function (ClientRequest $request, array $options) use (&$pinned): PromiseInterface {
        $pinned = $options['curl'][CURLOPT_RESOLVE] ?? null;

        return Http::response('', 200);
    });

    app(SignedWebhookSender::class)->send('https://hooks.example.com/in', 'e', [], 's');

    expect($pinned)->toBe(['hooks.example.com:443:93.184.216.34']);
});
