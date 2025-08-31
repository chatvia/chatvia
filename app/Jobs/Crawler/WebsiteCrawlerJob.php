<?php

namespace App\Jobs\Crawler;

use App\Data\Crawler\CrawlerData;
use App\Events\WebsiteCrawlerEvent;
use App\Models\AgentWebsite;
use App\Services\Crawler\Crawler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WebsiteCrawlerJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public AgentWebsite $website, public CrawlerData $data) {}

    public function handle(Crawler $crawler): void
    {
        $startTime = microtime(true);

        $links = $crawler->crawl($this->data);

        $endTime = microtime(true);

        $tookInSeconds = ceil($endTime - $startTime);

        $this->website->update([
            'links' => $links,
            'status' => 'crawled',
            'length' => count($links),
            'took_in_seconds' => $tookInSeconds,
            'job_id' => $this->job->getJobId(),
        ]);

        $this->website->refresh();

        WebsiteCrawlerEvent::dispatch($this->website);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->website->agent->id.':website:'.$this->website->id.':crawler';
    }
}
