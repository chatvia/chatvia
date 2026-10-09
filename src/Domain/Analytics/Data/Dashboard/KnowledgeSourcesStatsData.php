<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Distinct knowledge sources attached to the workspace's agents. `total` counts
 * files and websites; crawled pages belong to the websites.
 */
class KnowledgeSourcesStatsData extends Data
{
    public function __construct(
        public int $files,
        public int $websites,
        public int $crawled_pages,
        public int $total,
    ) {}
}
