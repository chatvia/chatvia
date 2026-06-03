<?php

namespace Domain\Knowledge\Source\Web\Data\Crawler;

use Spatie\LaravelData\Data;

class CrawlerData extends Data
{
    public function __construct(
        public string $url,
        public bool $slow_crawling = false,
        public ?int $limit = null,
        public ?string $include_paths = null,
        public ?string $exclude_paths = null,
    ) {}
}
