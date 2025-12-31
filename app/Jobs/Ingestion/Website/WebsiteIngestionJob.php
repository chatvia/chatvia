<?php

namespace App\Jobs\Ingestion\Website;

use App\Events\WebsiteCrawlerEvent;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Tidy\HTMLCleaner;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;

class WebsiteIngestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800; // 30 min

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SourceWebsite $website, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(HTMLCleaner $parser): void
    {
        $this->website->update(['status' => 'ingesting']);

        $this->website->refresh();

        WebsiteCrawlerEvent::dispatch($this->website, $this->agent);

        /** @var AgentWebsiteUrl<array> $untrainedUrls */
        $untrainedUrls = $this->website->untrainedUrls;

        $jobs = collect($untrainedUrls)->map(function (AgentWebsiteUrl $url) {
            return new WebsiteUrlTrainJob($url, $this->agent);
        });

        $websiteId = $this->website->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite ID: '.$this->website->id)
            ->then(function (Batch $batch) use ($websiteId) {
                $website = SourceWebsite::find($websiteId);
                $website->update(['status' => 'ingested']);
            })
            ->dispatch();

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $untrainedUrls->count(),
            'checksum' => hash('xxh128', $untrainedUrls->pluck('url')->implode('url', ';')),
        ]);
    }
}
