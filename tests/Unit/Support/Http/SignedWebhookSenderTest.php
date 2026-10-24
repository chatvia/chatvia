<?php

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;
use Support\Http\SignedWebhookSender;
use Support\Http\WebhookSignature;

/**
 * The shared sender behind the SIEM and CRM webhooks: it signs the exact body
 * it sends, re-checks the URL against SSRF at send time (outside local/testing),
 * connects to the addresses it checked (no second DNS lookup), and treats
 * any non-2xx answer — including a redirect it refuses to follow —
 * as a failed delivery.
 */
it('posts the payload signed with the shared webhook signature', function (): void {
    Http::fake(['*' => Http::response('', 200)]);

    app(SignedWebhookSender::class)->send(
        url: 'https://hooks.example.com/in',
        event: 'policy.changed',
        payload: ['a' => 1, 'b' => 'two'],
        secret: 'shh-secret',
    );

    Http::assertSent(function (ClientRequest $request): bool {
        $body = $request->body();

        return $request->url() === 'https://hooks.example.com/in'
            && $request->header('X-Chatvia-Event')[0] === 'policy.changed'
            && $request->header('X-Chatvia-Signature')[0] === 'sha256='.WebhookSignature::sign($body, 'shh-secret')
            && json_decode($body, true) === ['a' => 1, 'b' => 'two'];
    });
});

it('treats a redirect as a failed delivery instead of following it', function (): void {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);

    expect(fn () => app(SignedWebhookSender::class)->send('https://hooks.example.com/in', 'e', [], 's'))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

it('refuses internal targets at send time outside local and testing', function (string $url): void {
    app()['env'] = 'production';
    Http::fake();

    expect(fn () => app(SignedWebhookSender::class)->send($url, 'e', [], 's'))
        ->toThrow(UnsafeUrlException::class);

    Http::assertNothingSent();
})->with([
    'loopback' => 'https://127.0.0.1/hook',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data',
    'carrier-grade NAT' => 'https://100.64.0.1/hook',
    'IPv6 loopback literal' => 'https://[::1]/hook',
    'IPv6 unique-local literal' => 'https://[fd00::1]/hook',
    'IPv4-mapped IPv6 loopback' => 'https://[::ffff:127.0.0.1]/hook',
]);

it('sends a webhook to the address that was checked, even if DNS changes afterwards', function (): void {
    app()['env'] = 'production';
    $answers = [['93.184.216.34'], ['10.0.0.5']];
    SafeUrl::resolveHostsUsing(function (string $host) use (&$answers): array {
        return array_shift($answers) ?? ['10.0.0.5'];
    });

    $pinned = null;
    Http::fake(function (ClientRequest $request, array $options) use (&$pinned): PromiseInterface {
        $pinned = $options['curl'][CURLOPT_RESOLVE] ?? null;

        return Http::response('', 200);
    });

    try {
        app(SignedWebhookSender::class)->send('https://hooks.example.com/in', 'e', [], 's');
    } finally {
        SafeUrl::resolveHostsUsing(null);
    }

    expect($pinned)->toBe(['hooks.example.com:443:93.184.216.34']);
});
