<?php

namespace Domain\Knowledge\Source\Web\Jobs\Crawler;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Web\Actions\Crawl\CrawlWebsiteAction;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Services\Firecrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Polls an in-flight FireCrawl crawl. Because `/crawl` is asynchronous, we can't
 * block a single worker for the minutes a large site takes — instead each run
 * does one cheap status check and, while the crawl is still running, re-queues
 * itself with a short delay. A single run never approaches the queue's
 * retry-after window, so the crawl can run far longer than any one job's
 * timeout. On `completed` it finalises via CrawlWebsiteAction; on `failed` or
 * after `crawl_max_polls` checks it marks the website failed.
 */
class PollWebsiteCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public SourceWebsite $website,
        public CrawlerData $data,
        public Agent $agent,
        public string $crawlId,
        public int $startedAt,
        public ?string $internalJobId,
        public int $attempt,
    ) {
        $this->onQueue('ingestion.website');
    }

    public function handle(Firecrawl $firecrawl, CrawlWebsiteAction $action): void
    {
        $result = $firecrawl->fetchCrawl($this->crawlId);

        if ($result['status'] === 'completed') {
            $action->execute(
                $this->website,
                $this->data,
                $result['pages'],
                $this->agent,
                $this->startedAt,
                $this->internalJobId,
            );

            return;
        }

        if ($result['status'] === 'failed') {
            $this->markFailed();

            return;
        }

        if ($this->attempt >= self::maxPolls()) {
            report(new RuntimeException(
                "FireCrawl crawl {$this->crawlId} did not finish after ".self::maxPolls().' polls.'
            ));

            $this->markFailed();

            return;
        }

        self::dispatch(
            $this->website,
            $this->data,
            $this->agent,
            $this->crawlId,
            $this->startedAt,
            $this->internalJobId,
            $this->attempt + 1,
        )->delay(now()->addSeconds(self::pollSeconds()));
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed();

        report($exception);
    }

    public static function pollSeconds(): int
    {
        return max(2, (int) config('services.firecrawl.crawl_poll_seconds', 10));
    }

    public static function maxPolls(): int
    {
        return max(1, (int) config('services.firecrawl.crawl_max_polls', 120));
    }

    private function markFailed(): void
    {
        $this->website->update(['status' => 'failed']);

        WebsiteCrawlStarted::dispatch($this->website, $this->agent);
    }
}
