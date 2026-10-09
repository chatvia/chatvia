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
use Throwable;

/**
 * Discovers a website's pages off the request path. See
 * {@see CrawlWebsiteAction}.
 */
class WebsiteCrawlerJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceWebsite $website, public CrawlerData $data, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
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
