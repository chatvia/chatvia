<?php

namespace Domain\Ingestion\Jobs\Website;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Ingestion\Services\Tidy\HTMLCleaner;
use Domain\Sources\Web\Events\WebsiteCrawlerEvent;
use Domain\Sources\Web\Events\WebsiteIngestionProgressEvent;
use Domain\Sources\Web\Models\AgentWebsiteUrl;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class WebsiteIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

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

        $untrainedUrls = $this->website->untrainedUrls;

        $jobs = collect($untrainedUrls)->map(function (AgentWebsiteUrl $url) {
            return new WebsiteUrlTrainJob($url, $this->agent);
        });

        $websiteId = $this->website->id;
        $agentId = $this->agent->id;

        $batch = Bus::batch($jobs)
            ->name("Website Ingestion: agent={$agentId} website={$websiteId}")
            ->before(function (Batch $batch) use ($websiteId): void {
                SourceWebsite::query()
                    ->whereKey($websiteId)
                    ->update(['internal_chunking_batch_id' => $batch->id]);
            })
            ->then(function (Batch $batch) use ($websiteId, $agentId): void {
                $agent = Agent::find($agentId);
                $website = SourceWebsite::find($websiteId);

                if (! $agent || ! $website) {
                    return;
                }

                DB::transaction(function () use ($website): void {
                    $locked = SourceWebsite::query()
                        ->whereKey($website->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $locked) {
                        return;
                    }

                    $locked->update(['status' => 'ingested']);
                    $locked->decrement('expected_chunks', $locked->urls()->count());
                });

                $website->refresh();

                // Save entire chunks into file
                $text = $website->chunks()
                    ->select('chunk')
                    ->pluck('chunk')
                    ->implode("\n\n");

                $filePath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/websites/'.$website->id.'/'.Str::uuid()->toString().'.txt';

                Storage::put($filePath, $text);

                $website->update(['filepath' => $filePath]);

                SummaryIngestionJob::dispatchIf(config('ai.chunker.enhance') === true, $website);

                // Dispatch progress event (updates progress bar to 100%)
                WebsiteIngestionProgressEvent::dispatch($website, $agent->external_id);

                // Dispatch status event (moves website to "Trained" section)
                WebsiteCrawlerEvent::dispatch($website, $agent);
            })
            ->dispatch();

        $this->website->update([
            'internal_ingestion_job_id' => $this->job->getJobId(),
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $untrainedUrls->count(),
            'checksum' => hash('xxh128', $untrainedUrls->pluck('url')->implode('url', ';')),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->website->update(['status' => 'failed']);

        WebsiteCrawlerEvent::dispatch($this->website, $this->agent);

        report($e);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':sources:websites:'.$this->website->id;
    }
}
