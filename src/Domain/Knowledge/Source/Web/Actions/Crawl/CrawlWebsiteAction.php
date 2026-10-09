<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;
use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Illuminate\Support\Facades\DB;

/**
 * Discovers a website's pages and stores one URL row per page to train.
 *
 * FireCrawl's synchronous `/map` returns the site's links in one call. The
 * whole site is mapped (not only the seed's path, which would find nothing for
 * a deep link on a site that keeps its default language at the root), and the
 * include/exclude path globs are applied here, locally. Each page is scraped
 * later, when its URL is trained.
 */
class CrawlWebsiteAction
{
    public function __construct(
        private readonly Firecrawl $firecrawl,
        private readonly Crawler $crawler,
    ) {}

    public function execute(SourceWebsite $website, CrawlerData $data, Agent $agent, ?string $internalJobId = null): void
    {
        $startedAt = time();

        $links = $this->firecrawl->map(
            $this->crawler->rootUrl($data->url),
            $data->country,
            $data->languages,
            $data->limit ?? Crawler::DEFAULT_LIMIT,
        );

        // `/map` yields URLs only, so every page maps to empty Markdown.
        $selected = $this->crawler->filterPages($data, array_fill_keys($links, ''));

        // Idempotent: a retried job or a re-crawl must not stack duplicate URL
        // rows on top of a previous discovery. One transaction, so a failure
        // can't leave the website with its old URLs gone and no new ones.
        DB::transaction(function () use ($website, $selected, $startedAt, $internalJobId): void {
            $website->urls()->delete();

            $website->update([
                'status' => SourceStatus::Crawled,
                'took_in_seconds' => max(1, time() - $startedAt),
                'internal_ingestion_job_id' => $internalJobId,
            ]);

            $website->urls()->createMany(
                array_map(static fn (string $url): array => ['url' => $url], array_keys($selected))
            );
        });

        $website->refresh();

        WebsiteCrawlStarted::dispatch($website, $agent->external_id);
    }
}
