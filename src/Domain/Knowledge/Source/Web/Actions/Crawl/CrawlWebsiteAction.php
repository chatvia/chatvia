<?php

namespace Domain\Knowledge\Source\Web\Actions\Crawl;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
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
 *
 * A re-crawl keeps the rows of pages that are still found (they wait to be
 * trained again; an unchanged page is detected by its checksum and costs
 * nothing), removes the pages that are gone with the chunks only they used,
 * and adds the new ones.
 */
class CrawlWebsiteAction
{
    /** URL ids or rows per statement, far below PostgreSQL's parameter limit. */
    private const int ROWS_PER_STATEMENT = 1000;

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
            $this->replaceUrls($website, array_map(strval(...), array_keys($selected)));

            $website->update([
                'status' => SourceStatus::Crawled,
                'took_in_seconds' => max(1, time() - $startedAt),
                'internal_ingestion_job_id' => $internalJobId,
            ]);
        });

        $website->refresh();

        WebsiteCrawlStarted::dispatch($website, $agent->external_id);
    }

    /**
     * Makes the website's URL rows match the discovered pages.
     *
     * @param  list<string>  $urls
     */
    private function replaceUrls(SourceWebsite $website, array $urls): void
    {
        $wanted = array_flip($urls);
        $kept = [];
        $removedIds = [];

        foreach ($website->urls()->orderBy('id')->pluck('url', 'id') as $id => $url) {
            // A page no longer found, or a second row for the same page.
            if (! isset($wanted[$url]) || isset($kept[$url])) {
                $removedIds[] = (int) $id;

                continue;
            }

            $kept[$url] = true;
        }

        $this->removeUrls($removedIds);

        // Pages still found are trained again with the rest.
        $website->urls()
            ->whereNot('status', SourceStatus::Pending)
            ->update(['status' => SourceStatus::Pending, 'failure_reason' => null]);

        // Chunks only the removed pages used. The summary chunk stays.
        Chunk::query()->ofSource($website)->unreferenced()->delete();

        $this->insertUrls($website, array_values(array_filter($urls, fn (string $url): bool => ! isset($kept[$url]))));
    }

    /**
     * @param  list<int>  $urlIds
     */
    private function removeUrls(array $urlIds): void
    {
        $morphType = (new AgentWebsiteUrl)->getMorphClass();

        foreach (array_chunk($urlIds, self::ROWS_PER_STATEMENT) as $ids) {
            ChunkReference::query()
                ->where('referenceable_type', $morphType)
                ->whereIn('referenceable_id', $ids)
                ->delete();

            AgentWebsiteUrl::query()->whereIn('id', $ids)->delete();
        }
    }

    /**
     * @param  list<string>  $urls
     */
    private function insertUrls(SourceWebsite $website, array $urls): void
    {
        $now = now();

        foreach (array_chunk($urls, self::ROWS_PER_STATEMENT) as $batch) {
            AgentWebsiteUrl::query()->insert(array_map(static fn (string $url): array => [
                'source_website_id' => $website->id,
                'url' => $url,
                'status' => SourceStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $batch));
        }
    }
}
