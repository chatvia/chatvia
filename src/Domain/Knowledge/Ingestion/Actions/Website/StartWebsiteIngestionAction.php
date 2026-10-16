<?php

namespace Domain\Knowledge\Ingestion\Actions\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Enums\TrainingTrigger;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * Trains a website: runs one {@see WebsiteUrlTrainJob} per pending URL as a
 * Bus batch. One failed page must not cancel the others (`allowFailures`); the
 * batch's `finally` step runs {@see CompleteWebsiteIngestionAction}.
 */
class StartWebsiteIngestionAction
{
    public function execute(SourceWebsite $website, Agent $agent, ?string $internalJobId = null, TrainingTrigger $trigger = TrainingTrigger::Manual): void
    {
        $website->update(['status' => SourceStatus::Processing]);

        WebsiteCrawlStarted::dispatch($website->refresh(), $agent->external_id);

        $untrainedUrls = $website->untrainedUrls;

        $jobs = $untrainedUrls->map(fn (AgentWebsiteUrl $url): WebsiteUrlTrainJob => new WebsiteUrlTrainJob($url, $agent));

        // The callbacks are serialized with the batch, so they capture ids
        // only and load the models again when they run.
        $websiteId = $website->id;
        $agentId = $agent->id;
        $triggerValue = $trigger->value;

        // A batch pushes every job onto its own queue and ignores the one the
        // job names, so the batch names the website ingestion queue.
        $batch = Bus::batch($jobs->all())
            ->name("Website Ingestion: agent={$agentId} website={$websiteId}")
            ->onQueue('ingestion.website')
            ->before(static function (Batch $batch) use ($websiteId): void {
                SourceWebsite::query()
                    ->whereKey($websiteId)
                    ->update(['internal_chunking_batch_id' => $batch->id]);
            })
            ->allowFailures()
            ->finally(static function () use ($websiteId, $agentId, $triggerValue): void {
                $agent = Agent::find($agentId);
                $website = SourceWebsite::find($websiteId);

                // The website or the agent may have been deleted meanwhile.
                if ($agent !== null && $website !== null) {
                    app(CompleteWebsiteIngestionAction::class)->execute($website, $agent, TrainingTrigger::from($triggerValue));
                }
            })
            ->dispatch();

        $website->update([
            'internal_ingestion_job_id' => $internalJobId,
            'internal_chunking_batch_id' => $batch->id,
            'expected_chunks' => $untrainedUrls->count(),
            'checksum' => hash('xxh128', $untrainedUrls->pluck('url')->implode(';')),
        ]);
    }
}
