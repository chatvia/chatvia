<?php

namespace Domain\Knowledge\Source\Web\Services\Crawler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class CrawlerObserver extends CrawlObserver
{
    public function willCrawl(UriInterface $url, ?string $linkText): void {}

    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null,
    ): void {}

    public function finishedCrawling(): void {}
}
