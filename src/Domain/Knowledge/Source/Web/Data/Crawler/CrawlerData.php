<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Data\Crawler;

use Spatie\LaravelData\Data;

/**
 * How to discover a website's pages: the seed URL, an optional page limit, the
 * include/exclude path globs (comma separated, matched locally) and FireCrawl's
 * location options.
 */
class CrawlerData extends Data
{
    /**
     * @param  list<string>  $languages
     */
    public function __construct(
        public readonly string $url,
        public readonly ?int $limit = null,
        public readonly ?string $include_paths = null,
        public readonly ?string $exclude_paths = null,
        // FireCrawl `/map` `location` — proxy country + Accept-Language for the
        // fetch. Optional; does not filter path-localised sites.
        public readonly ?string $country = null,
        public readonly array $languages = [],
    ) {}
}
