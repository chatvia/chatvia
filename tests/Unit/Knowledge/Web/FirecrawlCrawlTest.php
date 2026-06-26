<?php

use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Wire-format coverage for the async `/crawl` flow that replaced the shallow
 * `/map` discovery. The key regression guard is that `crawlEntireDomain` is sent
 * — without it FireCrawl skips sibling sections (e.g. /ar, /fa) off the root,
 * which is what dropped the language variants.
 */
beforeEach(function (): void {
    config()->set('services.firecrawl.endpoint', 'https://firecrawl.test/v2');
    config()->set('services.firecrawl.key', 'fc-test');
    config()->set('services.firecrawl.timeout', 60000);
    config()->set('services.firecrawl.wait_for', 2000);
});

it('startCrawl() asks FireCrawl to crawl the entire domain and returns the job id', function (): void {
    Http::fake(['https://firecrawl.test/v2/crawl' => Http::response(['id' => 'job-1'], 200)]);

    $id = app(Firecrawl::class)->startCrawl('https://example.com', 1000, false);

    expect($id)->toBe('job-1');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://firecrawl.test/v2/crawl'
            && $request->hasHeader('Authorization', 'Bearer fc-test')
            && ($body['crawlEntireDomain'] ?? null) === true
            && ($body['sitemap'] ?? null) === 'include'
            && ($body['ignoreQueryParameters'] ?? null) === true
            && ($body['limit'] ?? null) === 1000
            && ($body['scrapeOptions']['formats'] ?? null) === ['markdown']
            && ($body['scrapeOptions']['onlyMainContent'] ?? null) === true
            && ($body['scrapeOptions']['waitFor'] ?? null) === 2000
            && ! array_key_exists('delay', $body)
            && ! array_key_exists('includePaths', $body)
            && ! array_key_exists('excludePaths', $body);
    });
});

it('startCrawl() forwards include/exclude path patterns to FireCrawl so the crawl is scoped at the source', function (): void {
    Http::fake(['https://firecrawl.test/v2/crawl' => Http::response(['id' => 'job-3'], 200)]);

    app(Firecrawl::class)->startCrawl(
        'https://example.com',
        1000,
        false,
        ['^/?blog/.*'],
        ['^/?admin.*'],
    );

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return ($body['includePaths'] ?? null) === ['^/?blog/.*']
            && ($body['excludePaths'] ?? null) === ['^/?admin.*'];
    });
});

it('startCrawl() adds a per-page delay when slow crawling is requested', function (): void {
    Http::fake(['https://firecrawl.test/v2/crawl' => Http::response(['id' => 'job-2'], 200)]);

    app(Firecrawl::class)->startCrawl('https://example.com', 500, true);

    Http::assertSent(fn (Request $request): bool => ($request->data()['delay'] ?? null) === 1);
});

it('startCrawl() throws when FireCrawl returns no job id', function (): void {
    Http::fake(['https://firecrawl.test/v2/crawl' => Http::response(['unexpected' => 'shape'], 200)]);

    expect(fn () => app(Firecrawl::class)->startCrawl('https://example.com'))
        ->toThrow(RuntimeException::class, 'did not return a job id');
});

it('fetchCrawl() returns only the status while the crawl is still running', function (): void {
    Http::fake(['https://firecrawl.test/v2/crawl/job-1' => Http::response(['status' => 'scraping'], 200)]);

    expect(app(Firecrawl::class)->fetchCrawl('job-1'))
        ->toBe(['status' => 'scraping', 'pages' => []]);
});

it('fetchCrawl() walks the paginated result and maps every page url to its markdown', function (): void {
    Http::fake([
        // Second page (followed via the absolute `next` cursor); has no further next.
        'https://firecrawl.test/v2/crawl/job-1?cursor=2' => Http::response([
            'status' => 'completed',
            'data' => [
                ['markdown' => 'B', 'metadata' => ['url' => 'https://example.com/fa']],
            ],
        ], 200),
        // First page.
        'https://firecrawl.test/v2/crawl/job-1' => Http::response([
            'status' => 'completed',
            'next' => 'https://firecrawl.test/v2/crawl/job-1?cursor=2',
            'data' => [
                ['markdown' => 'A', 'metadata' => ['sourceURL' => 'https://example.com/ar']],
                ['markdown' => '', 'metadata' => ['title' => 'no url, skipped']],
            ],
        ], 200),
    ]);

    expect(app(Firecrawl::class)->fetchCrawl('job-1'))->toBe([
        'status' => 'completed',
        'pages' => [
            'https://example.com/ar' => 'A',
            'https://example.com/fa' => 'B',
        ],
    ]);
});
