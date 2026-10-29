<?php

use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;

/**
 * filterPages() narrows raw crawl results to the ingest set: it keeps each URL's
 * markdown (so the scrape cache can be warmed in one pass), drops trailing-slash
 * duplicates, and applies the include/exclude path globs.
 */
it('filters the crawled pages by the path globs and drops trailing-slash duplicates', function (?string $include, ?string $exclude, array $pages, array $kept): void {
    $result = (new Crawler)->filterPages(new CrawlerData(url: 'https://x.test', include_paths: $include, exclude_paths: $exclude), $pages);

    expect($result)->toBe($kept);
})->with([
    'no filters: every page, one of each trailing-slash pair' => [null, null,
        ['https://x.test/' => 'home', 'https://x.test/blog/a' => 'first', 'https://x.test/blog/a/' => 'dupe', 'https://x.test/admin' => 'admin'],
        ['https://x.test/' => 'home', 'https://x.test/blog/a' => 'first', 'https://x.test/admin' => 'admin'],
    ],
    'only the included paths' => ['blog/*', null,
        ['https://x.test/blog/a' => 'a', 'https://x.test/blog/b' => 'b', 'https://x.test/admin' => 'admin'],
        ['https://x.test/blog/a' => 'a', 'https://x.test/blog/b' => 'b'],
    ],
    'without the excluded paths' => [null, 'admin*',
        ['https://x.test/blog/a' => 'a', 'https://x.test/admin' => 'admin', 'https://x.test/admin/users' => 'users'],
        ['https://x.test/blog/a' => 'a'],
    ],
]);
