<?php

namespace Domain\Knowledge\Source\Web\Actions\Source;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Jobs\Crawler\WebsiteCrawlerJob;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Discovers a website's pages again, with the path filters it was added with.
 * The new URL list replaces the old one when discovery finishes.
 */
class RecrawlSourceWebsiteAction
{
    public function execute(SourceWebsite $website, Agent $agent): void
    {
        $website->update(['status' => SourceStatus::Crawling]);

        $crawlerData = CrawlerData::from([
            'url' => $website->url,
            'include_paths' => $website->options['include_paths'] ?? null,
            'exclude_paths' => $website->options['exclude_paths'] ?? null,
        ]);

        WebsiteCrawlStarted::dispatch($website, $agent->external_id);
        WebsiteCrawlerJob::dispatch($website, $crawlerData, $agent);
    }
}
