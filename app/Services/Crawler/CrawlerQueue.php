<?php

namespace App\Services\Crawler;

use Spatie\Crawler\CrawlQueues\ArrayCrawlQueue;

class CrawlerQueue extends ArrayCrawlQueue
{
    public function getUrls(): array
    {
        return $this->urls;
    }
}
