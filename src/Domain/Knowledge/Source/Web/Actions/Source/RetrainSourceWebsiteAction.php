<?php

namespace Domain\Knowledge\Source\Web\Actions\Source;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteIngestionJob;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

class RetrainSourceWebsiteAction
{
    public function execute(SourceWebsite $website, Agent $agent, bool $recrawl): void
    {
        if ($recrawl) {
            $website->update(['status' => 'crawling']);

            $crawlerData = CrawlerData::from([
                'url' => $website->url,
                'include_paths' => $website->options['include_paths'] ?? null,
                'exclude_paths' => $website->options['exclude_paths'] ?? null,
            ]);

            WebsiteCrawlStarted::dispatch($website, $agent);
            WebsiteCrawlerJob::dispatch($website, $crawlerData, $agent);

            return;
        }

        WebsiteIngestionJob::dispatch($website, $agent);
    }
}
