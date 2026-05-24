<?php

namespace Domain\Sources\Web\Services\Crawler;

use Spatie\Crawler\CrawlQueues\ArrayCrawlQueue;
use Spatie\Crawler\CrawlUrl;

class CrawlerQueue extends ArrayCrawlQueue
{
    /**
     * @return array<CrawlUrl>
     */
    public function getUrls(): array
    {
        return $this->urls;
    }
}
