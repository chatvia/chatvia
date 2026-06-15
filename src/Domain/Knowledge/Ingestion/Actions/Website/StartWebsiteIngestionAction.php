<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\SummaryIngestionJob;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteIngestionProgressed;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StartWebsiteIngestionAction
{
    public function execute(SourceWebsite $website, Agent $agent, ?string $internalJobId = null): void
    {
        $website->update(['status' => SourceStatus::Processing->value]);

        $website->refresh();

        WebsiteCrawlStarted::dispatch($website, $agent);

        $untrainedUrls = $website->untrainedUrls;

        $jobs = collect($untrainedUrls)->map(function (AgentWebsiteUrl $url) use ($agent) {
            return new WebsiteUrlTrainJob($url, $agent);
        });

        $websiteId = $website->id;
        $agentId = $agent->id;

        $batch = Bus::batch($jobs)
            ->name("Website Ingestion: agent={$agentId} website={$websiteId}")
            ->before(function (Batch $batch) use ($websiteId): void {
                SourceWebsite::query()
                    ->whereKey($websiteId)
                    ->update(['internal_chunking_batch_id' => $batch->id]);
            })
            ->allowFailures() // one failed URL must not cancel the batch
            ->finally(function (Batch $batch) use ($websiteId, $agentId): void {
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

                    $locked->update(['status' => SourceStatus::Completed->value]);
                    $locked->decrement('expected_chunks', $locked->urls()->count());
                });

                $website->refresh();

                // Save entire chunks into file
                $text = $website->chunks()
                    ->select('chunk')
                    ->pluck('chunk')
                    ->implode("\n\n");

                $filePath = 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/websites/'.$website->id.'/'.Str::uuid()->toString().'.txt';

                Storage::put($filePath, $text);

                $website->update(['filepath' => $filePath]);

                SummaryIngestionJob::dispatchIf(config('chatvia.ai.chunker.enhance') === true, $website);

                WebsiteIngestionProgressed::dispatch($website, $agent->external_id);
                WebsiteCrawlStarted::dispatch($website, $agent);
            })
            ->dispatch();

        $website->update([
            'internal_ingestion_job_id' => $internalJobId,
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $untrainedUrls->count(),
            'checksum' => hash('xxh128', $untrainedUrls->pluck('url')->implode('url', ';')),
        ]);
    }
}
