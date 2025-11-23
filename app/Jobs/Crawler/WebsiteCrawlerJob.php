<?php

namespace App\Jobs\Crawler;

use App\Data\Crawler\CrawlerData;
use App\Events\WebsiteCrawlerEvent;
use App\Models\SourceWebsite;
use App\Services\Crawler\Crawler;
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

    public function __construct(public SourceWebsite $website, public CrawlerData $data, public int $agentId)
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

        WebsiteCrawlerEvent::dispatch($this->website, $this->agentId);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agentId.':website:'.$this->website->id.':crawler';
    }

    public function failed(?Throwable $exception): void
    {
        $this->website->update([
            'status' => 'failed',
        ]);

        report($exception);

        WebsiteCrawlerEvent::dispatch($this->website, $this->agentId);
    }
}
