<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;
use Domain\Knowledge\Source\Web\Services\WebPageMarkdownCache;

/**
 * Finalises a completed FireCrawl crawl: narrows the raw pages to the ingest
 * set, warms the scrape cache so stage-2 training reuses the crawled Markdown,
 * and persists one URL row per page. Invoked by PollWebsiteCrawlJob once the
 * async crawl reports `completed`.
 */
class CrawlWebsiteAction
{
    public function __construct(
        private readonly Crawler $crawler,
        private readonly WebPageMarkdownCache $markdownCache,
    ) {}

    /**
     * @param  array<string, string>  $pages  url => markdown from Firecrawl::fetchCrawl()
     * @param  int  $startedAt  epoch seconds the crawl was kicked off, for the duration figure
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

        // Stage 2 reads this back instead of re-scraping each page.
        foreach ($selected as $url => $markdown) {
            $this->markdownCache->put($url, $markdown);
        }

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
