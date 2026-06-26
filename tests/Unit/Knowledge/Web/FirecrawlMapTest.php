<?php

use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Wire-format coverage for the synchronous `/map` discovery that replaced the
 * async `/crawl` + poll flow.
 */
beforeEach(function (): void {
    config()->set('services.firecrawl.endpoint', 'https://firecrawl.test/v2');
    config()->set('services.firecrawl.key', 'fc-test');
    config()->set('services.firecrawl.timeout', 60000);
});

it('map() asks FireCrawl for the full link list and returns de-duplicated URLs', function (): void {
    Http::fake(['https://firecrawl.test/v2/map' => Http::response([
        'success' => true,
        'links' => [
            ['url' => 'https://example.com/en'],
            ['url' => 'https://example.com/en/pricing'],
            ['url' => 'https://example.com/en'], // duplicate, collapsed
        ],
    ], 200)]);

    $links = app(Firecrawl::class)->map('https://example.com', null, [], 1000);

    expect($links)->toBe([
        'https://example.com/en',
        'https://example.com/en/pricing',
    ]);

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://firecrawl.test/v2/map'
            && $request->hasHeader('Authorization', 'Bearer fc-test')
            && ($body['sitemap'] ?? null) === 'include'
            && ($body['includeSubdomains'] ?? null) === true
            && ($body['ignoreQueryParameters'] ?? null) === true
            && ($body['limit'] ?? null) === 1000
            && ! array_key_exists('location', $body);
    });
});

it('map() sends the location block when a country or languages are given', function (): void {
    Http::fake(['https://firecrawl.test/v2/map' => Http::response(['links' => []], 200)]);

    app(Firecrawl::class)->map('https://example.com', 'DE', ['de-DE']);

    Http::assertSent(fn (Request $request): bool => ($request->data()['location'] ?? null) === [
        'country' => 'DE',
        'languages' => ['de-DE'],
    ]);
});

it('map() tolerates a bare-string links payload', function (): void {
    Http::fake(['https://firecrawl.test/v2/map' => Http::response([
        'links' => ['https://example.com/a', 'https://example.com/b'],
    ], 200)]);

    expect(app(Firecrawl::class)->map('https://example.com'))
        ->toBe(['https://example.com/a', 'https://example.com/b']);
});
