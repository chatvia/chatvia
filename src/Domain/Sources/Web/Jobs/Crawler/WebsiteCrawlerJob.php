<?php

namespace Domain\Sources\Web\Jobs\Crawler;

use Domain\Sources\Web\Data\Crawler\CrawlerData;
use Domain\Sources\Web\Events\WebsiteCrawlerEvent;
use Domain\Agents\Models\Agent;
use Domain\Sources\Web\Models\SourceWebsite;
use Domain\Sources\Web\Services\Crawler\Crawler;
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

    public function handle(Crawler $crawler): void
    {
        $startTime = microtime(true);

        $urls = $crawler->crawl($this->data);

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        if (! $this->website) {
            return;
        }

        $this->website->update([
            'status' => 'crawled',
            'took_in_seconds' => $tookInSeconds,
            'job_id' => $this->job->getJobId(),
        ]);

        $this->website->urls()->createMany(
            array_map(static fn ($url) => ['url' => $url], $urls)
        );

        $this->website->refresh();

        WebsiteCrawlerEvent::dispatch($this->website, $this->agent);
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

        WebsiteCrawlerEvent::dispatch($this->website, $this->agent);
    }
}
