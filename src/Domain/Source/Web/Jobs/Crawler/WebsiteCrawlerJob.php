<?php

namespace Domain\Source\Web\Jobs\Crawler;

use Domain\Agent\Models\Agent;
use Domain\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Source\Web\Data\Crawler\CrawlerData;
use Domain\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Source\Web\Models\SourceWebsite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class WebsiteCrawlerJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceWebsite $website, public CrawlerData $data, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(CrawlWebsiteAction $action): void
    {
        $action->execute($this->website, $this->data, $this->agent, $this->job->getJobId());
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
