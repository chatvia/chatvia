<?php

namespace Domain\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Source\Web\Data\Crawler\CrawlerData;
use Domain\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Source\Web\Models\SourceWebsite;
use Domain\Source\Web\Services\Crawler\Crawler;

class CrawlWebsiteAction
{
    public function __construct(
        private readonly Crawler $crawler,
    ) {}

    public function execute(SourceWebsite $website, CrawlerData $data, Agent $agent, ?string $internalJobId = null): void
    {
        $startTime = microtime(true);

        $urls = $this->crawler->crawl($data);

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        $website->update([
            'status' => 'crawled',
            'took_in_seconds' => $tookInSeconds,
            'job_id' => $internalJobId,
        ]);

        $website->urls()->createMany(
            array_map(static fn ($url): array => ['url' => $url], $urls)
        );

        $website->refresh();

        WebsiteCrawlStarted::dispatch($website, $agent);
    }
}
