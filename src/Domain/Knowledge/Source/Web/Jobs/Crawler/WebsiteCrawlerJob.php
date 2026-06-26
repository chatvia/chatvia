<?php

namespace Domain\Knowledge\Source\Web\Jobs\Crawler;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Crawler\Crawler;
use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Kicks off FireCrawl's async `/crawl` and hands the job id to
 * PollWebsiteCrawlJob, which polls until the crawl finishes. This job itself
 * only fires the request, so it stays well within the queue's retry window —
 * the long wait lives in the poll chain, where each run is a single fast status
 * check.
 */
class WebsiteCrawlerJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceWebsite $website, public CrawlerData $data, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(Firecrawl $firecrawl): void
    {
        $crawlId = $firecrawl->startCrawl(
            $this->data->url,
            $this->data->limit ?? Crawler::DEFAULT_LIMIT,
            $this->data->slow_crawling,
        );

        PollWebsiteCrawlJob::dispatch(
            $this->website,
            $this->data,
            $this->agent,
            $crawlId,
            time(),
            $this->job->getJobId(),
            1,
        )->delay(now()->addSeconds(PollWebsiteCrawlJob::pollSeconds()));
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
