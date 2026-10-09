<?php

namespace Domain\Knowledge\Ingestion\Actions\Pipeline;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Events\TextIngestionStarted;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Events\WebsiteCrawlStarted;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Throwable;

/**
 * Records that ingesting a source failed for good: reports the exception,
 * sets the status to failed with the reason, and pushes the new status to the
 * page that shows the source. The ingestion jobs call it from their `failed()`
 * hook, once the queue has given up on them.
 *
 * File failures are not broadcast: the Files page shows the status on its next
 * load, as it always has.
 */
class MarkSourceFailedAction
{
    public function execute(SourceFile|SourceText|SourceWebsite|AgentWebsiteUrl $source, Agent $agent, ?Throwable $exception = null): void
    {
        if ($exception instanceof Throwable) {
            report($exception);
        }

        $source->update([
            'status' => SourceStatus::Failed,
            'failure_reason' => $exception?->getMessage(),
        ]);

        match (true) {
            $source instanceof SourceText => TextIngestionStarted::dispatch($source, $agent->external_id),
            $source instanceof SourceWebsite => WebsiteCrawlStarted::dispatch($source, $agent->external_id),
            $source instanceof AgentWebsiteUrl => UrlIngestionStarted::dispatch($source, $agent->external_id),
            $source instanceof SourceFile => null,
        };
    }
}
