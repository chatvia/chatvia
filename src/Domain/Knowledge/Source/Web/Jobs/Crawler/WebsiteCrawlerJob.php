<?php

namespace Domain\Knowledge\Source\Web\Jobs\Crawler;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Discovers a website's pages off the request path. See
 * {@see CrawlWebsiteAction}.
 */
#[Queue('ingestion.website')]
// Longer than a run, so a worker that dies mid-run cannot hold the lock for good.
#[UniqueFor(3600)]
// A website deleted before the job runs needs no crawl.
#[DeleteWhenMissingModels]
class WebsiteCrawlerJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public SourceWebsite $website, public CrawlerData $data, public Agent $agent) {}

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
