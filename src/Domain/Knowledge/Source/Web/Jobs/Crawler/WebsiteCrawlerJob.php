<?php

namespace Domain\Knowledge\Source\Web\Jobs\Crawler;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;
use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Discovers a website's URLs via FireCrawl's synchronous `/map` and persists
 * them through CrawlWebsiteAction. `/map` returns in a single fast call, so —
 * unlike the old async `/crawl` flow — there is no job id to poll: this one job
 * does discovery end to end.
 */
class WebsiteCrawlerJob implements ShouldBeUnique, ShouldQueue
{
    use \Illuminate\Foundation\Queue\Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceWebsite $website, public CrawlerData $data, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(Firecrawl $firecrawl, CrawlWebsiteAction $action, Crawler $crawler): void
    {
        $startedAt = time();

        // Map the whole site, not the seed's path subtree — `/map` would return
        // nothing for a deep link like `…/en` on a default-locale-at-root site.
        $links = $firecrawl->map(
            $crawler->rootUrl($this->data->url),
            $this->data->country,
            $this->data->languages,
            $this->data->limit ?? Crawler::DEFAULT_LIMIT,
        );

        // `/map` yields URLs only — no Markdown to warm the scrape cache with, so
        // each URL maps to an empty string and stage 2 scrapes it on demand.
        $pages = array_fill_keys($links, '');

        $action->execute(
            $this->website,
            $this->data,
            $pages,
            $this->agent,
            $startedAt,
            $this->job?->getJobId(),
        );
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->external_id.':website:'.$this->website->id.':crawler';
    }

    public function failed(?Throwable $exception): void
    {
        $this->website->update([
            'status' => 'failed',
        ]);

        report($exception);

        WebsiteCrawlStarted::dispatch($this->website, $this->agent);
    }
}
