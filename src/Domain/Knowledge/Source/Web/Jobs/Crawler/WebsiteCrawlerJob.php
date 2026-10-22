<?php

namespace Domain\Knowledge\Source\Web\Jobs\Crawler;

use DateTimeInterface;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Middleware\ThrottleFirecrawl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Discovers a website's pages off the request path. See
 * {@see CrawlWebsiteAction}.
 */
// The website-ingestion queue, on the long supervisor that crawls take.
#[Queue('ingestion.website')]
// As long as retryUntil(), so a crawl waiting on the FireCrawl throttle is never
// queued twice; the lock still expires, so a dead worker cannot hold it for good.
#[UniqueFor(21600)]
// Cap on genuine crawl failures; throttle releases don't count and are
// bounded by retryUntil() instead.
#[MaxExceptions(3)]
// A website deleted before the job runs needs no crawl.
#[DeleteWhenMissingModels]
class WebsiteCrawlerJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SourceWebsite $website, public readonly CrawlerData $data, public readonly Agent $agent) {}

    /**
     * The FireCrawl throttle: a throttled discovery run is released before
     * any FireCrawl call is made, like every other FireCrawl-calling job.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ThrottleFirecrawl];
    }

    /**
     * Throttle releases re-queue the job without using up tries, so bound the
     * retries by wall-clock and let `#[MaxExceptions]` cap real failures.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(CrawlWebsiteAction $action): void
    {
        $action->execute($this->website, $this->data, $this->agent, $this->job?->getJobId());
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->external_id.':website:'.$this->website->id.':crawler';
    }

    public function failed(?Throwable $exception): void
    {
        app(MarkSourceFailedAction::class)->execute($this->website, $this->agent, $exception);
    }
}
