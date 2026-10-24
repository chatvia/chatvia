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
 * `/me/businesses` → `owned_pages`/`client_pages` and dedupes, so a business
 * customer's Page connects. The token exchanges carry the Meta app secret: it
 * travels in a POST body (never in a URL that logs or exception messages can
 * record), and a transport failure never writes it or a token to the log.
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

it('falls back to business-owned Pages when /me/accounts is empty', function (): void {
    Http::fake([
        'graph.facebook.com/v25.0/me/accounts*' => Http::response(['data' => []]),
        'graph.facebook.com/v25.0/me/businesses*' => Http::response(['data' => [['id' => 'BIZ-1', 'name' => 'Acme Portfolio']]]),
        'graph.facebook.com/v25.0/BIZ-1/owned_pages*' => Http::response(['data' => [
            ['id' => 'PAGE-1', 'name' => 'ChatVia', 'access_token' => 'EAAG-page-token'],
        ]]),
        'graph.facebook.com/v25.0/BIZ-1/client_pages*' => Http::response(['data' => []]),
    ]);

    $pages = (new MetaOAuthClient)->pages('USER-TOKEN');

    expect($pages)->toHaveCount(1)
        ->and($pages[0]->id)->toBe('PAGE-1')
        ->and($pages[0]->name)->toBe('ChatVia')
        ->and($pages[0]->access_token)->toBe('EAAG-page-token');
});

it('also picks up client (agency-managed) Pages', function (): void {
    Http::fake([
        'graph.facebook.com/v25.0/me/accounts*' => Http::response(['data' => []]),
        'graph.facebook.com/v25.0/me/businesses*' => Http::response(['data' => [['id' => 'BIZ-1', 'name' => 'Agency']]]),
        'graph.facebook.com/v25.0/BIZ-1/owned_pages*' => Http::response(['data' => []]),
        'graph.facebook.com/v25.0/BIZ-1/client_pages*' => Http::response(['data' => [
            ['id' => 'PAGE-9', 'name' => 'Client Page', 'access_token' => 'EAAG-client'],
        ]]),
    ]);

    $pages = (new MetaOAuthClient)->pages('USER-TOKEN');

    expect($pages)->toHaveCount(1)
        ->and($pages[0]->id)->toBe('PAGE-9');
});

it('keeps the personal copy of a Page that also comes through a business', function (): void {
    Http::fake([
        'graph.facebook.com/v25.0/me/accounts*' => Http::response(['data' => [
            ['id' => 'PAGE-1', 'name' => 'ChatVia', 'access_token' => 'EAAG-personal'],
        ]]),
        'graph.facebook.com/v25.0/me/businesses*' => Http::response(['data' => [['id' => 'BIZ-1', 'name' => 'Acme']]]),
        'graph.facebook.com/v25.0/BIZ-1/owned_pages*' => Http::response(['data' => [
            ['id' => 'PAGE-1', 'name' => 'ChatVia', 'access_token' => 'EAAG-business'],
        ]]),
        'graph.facebook.com/v25.0/BIZ-1/client_pages*' => Http::response(['data' => []]),
    ]);

    $pages = (new MetaOAuthClient)->pages('USER-TOKEN');

    expect($pages)->toHaveCount(1)
        ->and($pages[0]->access_token)->toBe('EAAG-personal');
});

it('sends the user and page tokens as bearer tokens, never in the URL', function (): void {
    Http::fake([
        'graph.facebook.com/v25.0/me/accounts*' => Http::response(['data' => [
            ['id' => 'PAGE-1', 'name' => 'ChatVia', 'access_token' => 'EAAG-page-token'],
        ]]),
        'graph.facebook.com/v25.0/me/businesses*' => Http::response(['data' => [['id' => 'BIZ-1', 'name' => 'Acme']]]),
        'graph.facebook.com/v25.0/BIZ-1/*' => Http::response(['data' => []]),
        'graph.facebook.com/v25.0/PAGE-1*' => Http::response(['instagram_business_account' => ['id' => 'IG-1', 'username' => 'chatvia']]),
    ]);

    (new MetaOAuthClient)->pagesWithInstagram('USER-TOKEN');

    Http::assertSentCount(5);
    Http::assertNotSent(fn (Request $request): bool => Str::contains($request->url(), 'access_token='));
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/me/accounts')
        && $request->header('Authorization') === ['Bearer USER-TOKEN']);
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/BIZ-1/owned_pages')
        && $request->header('Authorization') === ['Bearer USER-TOKEN']);
    Http::assertSent(fn (Request $request): bool => Str::contains($request->url(), '/PAGE-1?')
        && $request->header('Authorization') === ['Bearer EAAG-page-token']);
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
        && ! Str::contains($request->url(), 'META-APP-SECRET')
        && ! Str::contains($request->url(), '?')
        && ($request->data()['client_secret'] ?? null) === 'META-APP-SECRET');
});

it('sends the Embedded Signup exchange as a POST without secrets in the URL', function (): void {
    Http::fake(['graph.facebook.com/v25.0/oauth/access_token' => Http::response(['access_token' => 'BUSINESS-TOKEN'])]);

    expect((new MetaOAuthClient)->exchangeEmbeddedSignupCode('ES-CODE'))->toBe('BUSINESS-TOKEN');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && ! Str::contains($request->url(), 'ES-CODE')
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
