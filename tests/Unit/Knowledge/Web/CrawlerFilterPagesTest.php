<?php

use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;

/**
 * filterPages() narrows raw crawl results to the ingest set: it keeps each URL's
 * markdown (so the scrape cache can be warmed in one pass), drops trailing-slash
 * duplicates, and applies the include/exclude path globs.
 */
it('keeps every page and drops trailing-slash duplicates when no filters are set', function (): void {
    $pages = [
        'https://x.test/' => 'home',
        'https://x.test/blog/a' => 'first',
        'https://x.test/blog/a/' => 'dupe',
        'https://x.test/admin' => 'admin',
    ];

    $result = (new Crawler)->filterPages(new CrawlerData(url: 'https://x.test'), $pages);

    expect($result)->toBe([
        'https://x.test/' => 'home',
        'https://x.test/blog/a' => 'first',
        'https://x.test/admin' => 'admin',
    ]);
});

it('keeps only included paths', function (): void {
    $pages = [
        'https://x.test/blog/a' => 'a',
        'https://x.test/blog/b' => 'b',
        'https://x.test/admin' => 'admin',
    ];

    $result = (new Crawler)->filterPages(
        new CrawlerData(url: 'https://x.test', include_paths: 'blog/*'),
        $pages,
    );

    expect($result)->toBe([
        'https://x.test/blog/a' => 'a',
        'https://x.test/blog/b' => 'b',
    ]);
});

it('drops excluded paths', function (): void {
    $pages = [
        'https://x.test/blog/a' => 'a',
        'https://x.test/admin' => 'admin',
        'https://x.test/admin/users' => 'users',
    ];

    $result = (new Crawler)->filterPages(
        new CrawlerData(url: 'https://x.test', exclude_paths: 'admin*'),
        $pages,
    );

    expect($result)->toBe(['https://x.test/blog/a' => 'a']);
});
