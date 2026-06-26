<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;
use Domain\Knowledge\Source\Web\Services\WebPageMarkdownCache;

/**
 * Finalises FireCrawl discovery: narrows the discovered pages to the ingest set,
 * warms the scrape cache for any page that arrived with Markdown, and persists
 * one URL row per page. Invoked by WebsiteCrawlerJob once `/map` returns.
 */
class CrawlWebsiteAction
{
    public function __construct(
        private readonly Crawler $crawler,
        private readonly WebPageMarkdownCache $markdownCache,
    ) {}

    /**
     * @param  array<string, string>  $pages  url => markdown (markdown is '' for `/map` discovery)
     * @param  int  $startedAt  epoch seconds discovery was kicked off, for the duration figure
     */
    public function execute(
        SourceWebsite $website,
        CrawlerData $data,
        array $pages,
        Agent $agent,
        int $startedAt,
        ?string $internalJobId = null,
    ): void {
        $selected = $this->crawler->filterPages($data, $pages);

        // Stage 2 reads this back instead of re-scraping each page. `/map`
        // discovery carries no Markdown (empty string), so there is nothing to
        // cache — stage 2 scrapes those on demand.
        foreach ($selected as $url => $markdown) {
            if ($markdown !== '') {
                $this->markdownCache->put($url, $markdown);
            }
        }

        // Idempotent: a retried job or a re-crawl must not stack duplicate URL
        // rows on top of a previous discovery.
        $website->urls()->delete();

        $website->update([
            'status' => 'crawled',
            'took_in_seconds' => max(1, time() - $startedAt),
            'internal_ingestion_job_id' => $internalJobId,
        ]);

        $website->urls()->createMany(
            array_map(static fn (string $url): array => ['url' => $url], array_keys($selected))
        );

        $website->refresh();

        WebsiteCrawlStarted::dispatch($website, $agent);
    }
}
