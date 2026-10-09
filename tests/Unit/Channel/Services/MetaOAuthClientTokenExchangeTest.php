<?php

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The token exchanges carry the Meta app secret. It must travel in a POST
 * body (never in a URL that logs or exception messages can record), and a
 * transport failure must not write it to the log.
 */
beforeEach(function (): void {
    config([
        'services.meta.graph_version' => 'v25.0',
        'services.meta.messenger.app_id' => 'APP-ID',
        'services.meta.messenger.app_secret' => 'META-APP-SECRET',
        'services.meta.messenger.redirect' => 'https://chatvia.test/oauth/meta/callback',
        'services.meta.whatsapp.app_id' => 'WA-APP-ID',
        'services.meta.whatsapp.app_secret' => 'META-APP-SECRET',
    ]);
});

it('sends the app secret in the POST body for both token exchanges', function (): void {
    Http::fake([
        'graph.facebook.com/v25.0/oauth/access_token' => Http::sequence()
            ->push(['access_token' => 'SHORT-TOKEN'])
            ->push(['access_token' => 'LONG-TOKEN']),
    ]);

    $token = (new MetaOAuthClient)->exchangeCodeForUserToken(MetaApp::Messenger, 'THE-CODE');

    expect($token)->toBe('LONG-TOKEN');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && ! str_contains($request->url(), 'META-APP-SECRET')
        && ! str_contains($request->url(), '?')
        && ($request->data()['client_secret'] ?? null) === 'META-APP-SECRET');
});

it('sends the Embedded Signup exchange as a POST without secrets in the URL', function (): void {
    Http::fake(['graph.facebook.com/v25.0/oauth/access_token' => Http::response(['access_token' => 'BUSINESS-TOKEN'])]);

    expect((new MetaOAuthClient)->exchangeEmbeddedSignupCode('ES-CODE'))->toBe('BUSINESS-TOKEN');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && ! str_contains($request->url(), 'ES-CODE')
        && ($request->data()['code'] ?? null) === 'ES-CODE');
});

it('never logs the app secret or token when a Graph call fails in transport', function (): void {
    Log::spy();

    Http::fake(function (): void {
        throw new ConnectionException('cURL error 28: Operation timed out for https://graph.facebook.com/v25.0/me/accounts?access_token=USER-TOKEN-VALUE&client_secret=META-APP-SECRET');
    });

    expect((new MetaOAuthClient)->pages('USER-TOKEN-VALUE'))->toBe([]);

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []): bool {
        $logged = $message.json_encode($context);

        return ! str_contains($logged, 'META-APP-SECRET') && ! str_contains($logged, 'USER-TOKEN-VALUE');
    });
    Log::shouldNotHaveReceived('info');
});
