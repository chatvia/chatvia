<?php

use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * `/me/accounts` only lists Pages the user manages personally — Pages owned by a
 * Business Portfolio come back empty there. `pages()` must also traverse
 * `/me/businesses` → `owned_pages`/`client_pages` (which return the page token
 * directly) and dedupe, so a business customer's Page connects.
 */
beforeEach(function (): void {
    config(['services.meta.graph_version' => 'v25.0']);
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

it('dedupes a Page that appears both personally and via a business (personal wins)', function (): void {
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
        ->and($pages[0]->access_token)->toBe('EAAG-personal'); // first write wins
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
