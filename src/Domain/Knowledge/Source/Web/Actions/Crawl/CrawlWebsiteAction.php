<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;
use Illuminate\Support\Facades\DB;

/**
 * Finalises FireCrawl discovery: narrows the discovered pages to the ingest set
 * and persists one URL row per page. Invoked by WebsiteCrawlerJob once `/map`
 * returns; each page is scraped later, when its URL is trained.
 */
class CrawlWebsiteAction
{
    public function __construct(
        private readonly Crawler $crawler,
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

        // Idempotent: a retried job or a re-crawl must not stack duplicate URL
        // rows on top of a previous discovery. One transaction, so a failure
        // can't leave the website with its old URLs gone and no new ones.
        DB::transaction(function () use ($website, $selected, $startedAt, $internalJobId): void {
            $website->urls()->delete();

            $website->update([
                'status' => 'crawled',
                'took_in_seconds' => max(1, time() - $startedAt),
                'internal_ingestion_job_id' => $internalJobId,
            ]);

            $website->urls()->createMany(
                array_map(static fn (string $url): array => ['url' => $url], array_keys($selected))
            );
        });

        $website->refresh();

        WebsiteCrawlStarted::dispatch($website, $agent);
    }
}
