<?php

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The Facebook Graph calls of the Meta connect flows. `/me/accounts` only
 * lists Pages the user manages personally, so `pages()` also walks
 * `/me/businesses` → `owned_pages`/`client_pages` and dedupes, keeping the
 * personal copy, so a business customer's Page connects. Tokens travel as
 * bearer tokens and the Meta app secret in a POST body, never in a URL that
 * logs or exception messages can record, and a transport failure never
 * writes the secret or a token to the log.
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

it('lists personal, business-owned and client Pages once each, keeping the personal copy', function (): void {
    Http::fake([
        'graph.facebook.com/v25.0/me/accounts*' => Http::response(['data' => [
            ['id' => 'PAGE-1', 'name' => 'ChatVia', 'access_token' => 'EAAG-personal'],
        ]]),
        'graph.facebook.com/v25.0/me/businesses*' => Http::response(['data' => [['id' => 'BIZ-1', 'name' => 'Acme Portfolio']]]),
        'graph.facebook.com/v25.0/BIZ-1/owned_pages*' => Http::response(['data' => [
            ['id' => 'PAGE-1', 'name' => 'ChatVia', 'access_token' => 'EAAG-business'],
            ['id' => 'PAGE-2', 'name' => 'Acme', 'access_token' => 'EAAG-owned'],
        ]]),
        'graph.facebook.com/v25.0/BIZ-1/client_pages*' => Http::response(['data' => [
            ['id' => 'PAGE-9', 'name' => 'Client Page', 'access_token' => 'EAAG-client'],
        ]]),
    ]);

    $pages = collect((new MetaOAuthClient)->pages('USER-TOKEN'))->keyBy('id');

    expect($pages->keys()->sort()->values()->all())->toBe(['PAGE-1', 'PAGE-2', 'PAGE-9'])
        ->and($pages['PAGE-1']->access_token)->toBe('EAAG-personal')
        ->and($pages['PAGE-2']->name)->toBe('Acme')
        ->and($pages['PAGE-9']->access_token)->toBe('EAAG-client');
});

it('keeps tokens and the app secret out of every URL', function (): void {
    Http::fake([
        'graph.facebook.com/v25.0/oauth/access_token' => Http::sequence()
            ->push(['access_token' => 'SHORT-TOKEN'])
            ->push(['access_token' => 'LONG-TOKEN'])
            ->push(['access_token' => 'BUSINESS-TOKEN']),
        'graph.facebook.com/v25.0/me/accounts*' => Http::response(['data' => [
            ['id' => 'PAGE-1', 'name' => 'ChatVia', 'access_token' => 'EAAG-page-token'],
        ]]),
        'graph.facebook.com/v25.0/me/businesses*' => Http::response(['data' => [['id' => 'BIZ-1', 'name' => 'Acme']]]),
        'graph.facebook.com/v25.0/BIZ-1/*' => Http::response(['data' => []]),
        'graph.facebook.com/v25.0/PAGE-1*' => Http::response(['instagram_business_account' => ['id' => 'IG-1', 'username' => 'chatvia']]),
    ]);
    $client = new MetaOAuthClient;

    $client->pagesWithInstagram('USER-TOKEN');
    $token = $client->exchangeCodeForUserToken(MetaApp::Messenger, 'THE-CODE');
    $business = $client->exchangeEmbeddedSignupCode('ES-CODE');

    expect($token)->toBe('LONG-TOKEN')
        ->and($business)->toBe('BUSINESS-TOKEN');
    Http::assertSentCount(8);
    Http::assertNotSent(fn (Request $request): bool => Str::contains($request->url(), ['access_token=', 'META-APP-SECRET', 'ES-CODE']));
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/me/accounts')
        && $request->header('Authorization') === ['Bearer USER-TOKEN']);
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/BIZ-1/owned_pages')
        && $request->header('Authorization') === ['Bearer USER-TOKEN']);
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/PAGE-1?')
        && $request->header('Authorization') === ['Bearer EAAG-page-token']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && ! Str::contains($request->url(), '?')
        && ($request->data()['client_secret'] ?? null) === 'META-APP-SECRET');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
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

        return ! Str::contains($logged, 'META-APP-SECRET') && ! Str::contains($logged, 'USER-TOKEN-VALUE');
    });
    Log::shouldNotHaveReceived('info');
});
