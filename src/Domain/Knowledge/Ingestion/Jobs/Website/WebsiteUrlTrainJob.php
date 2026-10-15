<?php

namespace Domain\Knowledge\Ingestion\Jobs\Website;

use DateTimeInterface;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Ingestion\Actions\Website\TrainWebsiteUrlAction;
use Domain\Knowledge\Ingestion\Services\BatchProgress;
use Domain\Knowledge\Source\Web\Middleware\ThrottleFirecrawl;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Scrapes and chunks one page of a website. See {@see TrainWebsiteUrlAction}.
 * FireCrawl calls are rate limited across all workers by
 * {@see ThrottleFirecrawl}.
 */
// Inside its batch the batch's own queue wins, so
// StartWebsiteIngestionAction names the same one.
#[Queue('ingestion.website')]
// Cap on genuine processing errors (scrape/embedding failures). FireCrawl
// rate-limit releases from ThrottleFirecrawl go back to the queue without
// throwing, so they don't count here — they're bounded by retryUntil()
// instead. Keeps a throttled URL from being marked "failed" just because
// the budget was busy.
#[MaxExceptions(3)]
// Longer and longer waits, so a busy scrape or model service can catch up.
#[Backoff(10, 60, 300)]
// A page waits on the scrape and on model calls for its chunks.
#[Timeout(800)]
// As long as retryUntil(), so a URL waiting on the FireCrawl throttle is never queued twice.
#[UniqueFor(21600)]
// A page deleted before the job runs needs no training.
#[DeleteWhenMissingModels]
class WebsiteUrlTrainJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public AgentWebsiteUrl $websiteUrl, public Agent $agent) {}

    /**
     * The FireCrawl throttle: a throttled job is released before any page is
     * scraped or any AI call is made.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ThrottleFirecrawl];
    }

    /**
     * Throttle releases ({@see ThrottleFirecrawl}) re-queue the job repeatedly
     * while the FireCrawl budget is saturated, so a fixed number of tries would
     * burn out before a busy URL ever ran. Bound retries by wall-clock instead —
     * long enough to drain even a large site at the configured per-minute rate —
     * and let `#[MaxExceptions]` cap real failures.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(TrainWebsiteUrlAction $action): void
    {
        $action->execute($this->websiteUrl, $this->agent, BatchProgress::afterCurrentJob($this->batch()));
    }

    public function failed(Throwable $e): void
    {
        app(MarkSourceFailedAction::class)->execute($this->websiteUrl, $this->agent, $e);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':websites:url:'.$this->websiteUrl->url.':train:';
    }
}
