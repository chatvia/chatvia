<?php

namespace App\Jobs\Ingestion\Website;

use App\Events\WebsiteCrawlerEvent;
use App\Events\WebsiteIngestionProgressEvent;
use App\Jobs\Ingestion\SummaryIngestionJob;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\SourceWebsite;
use App\Services\Tidy\HTMLCleaner;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebsiteIngestionJob implements ShouldQueue, ShouldBeUnique
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

        $agenId = $this->agent->id;

        $batch = Bus::batch($jobs)
            ->name('Ingestion Agent WebSite ID: '.$this->website->id)
            ->then(function (Batch $batch) use ($websiteId, $agenId) {
                $agent = Agent::find($agenId);
                $website = SourceWebsite::find($websiteId);

                $website->update(['status' => 'ingested']);

                $website->decrement('expected_chunks', $website->urls()->count());

                // Save entire chunks into file
                $text = $website->chunks()
                    ->select('chunk')
                    ->pluck('chunk')
                    ->implode("\n\n");

                $filePath = 'users/'.$agent->user_id.'/agents/'.$agent.'/websites/'.$website->id.'/'.Str::uuid()->toString().'.txt';

                Storage::put($filePath, $text);

                $website->update(['filepath' => $filePath]);

                SummaryIngestionJob::dispatchIf(config('ai.chunker.enhance') === true, $website);

                // Dispatch progress event (updates progress bar to 100%)
                WebsiteIngestionProgressEvent::dispatch($website, $agent->external_id);

                // Dispatch status event (moves website to "Trained" section)
                WebsiteCrawlerEvent::dispatch($website, $agent);

                // Enhance
            })
            ->dispatch();

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $untrainedUrls->count(),
            'checksum' => hash('xxh128', $untrainedUrls->pluck('url')->implode('url', ';')),
        ]);
    }

    public function uniqueId()
    {
        return 'agents:'.$this->agent->id.':sources:websites:'.$this->website->id;
    }
}
