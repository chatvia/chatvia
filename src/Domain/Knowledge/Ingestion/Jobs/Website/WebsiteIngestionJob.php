<?php

namespace Domain\Knowledge\Ingestion\Jobs\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Pipeline\MarkSourceFailedAction;
use Domain\Knowledge\Ingestion\Actions\Website\StartWebsiteIngestionAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Starts training a website's pending URLs. See
 * {@see StartWebsiteIngestionAction}.
 */
#[Queue('ingestion.website')]
// One try: a failed start goes straight to failed(), which marks the website failed.
#[Tries(1)]
// Below the `redis-long` connection's `retry_after`, so no second worker picks it up mid-run.
#[Timeout(1800)]
// Longer than a run, so a worker that dies mid-run cannot hold the lock for good.
#[UniqueFor(3600)]
// A website deleted before the job runs needs no training.
#[DeleteWhenMissingModels]
class WebsiteIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public SourceWebsite $website, public Agent $agent) {}

    public function handle(StartWebsiteIngestionAction $action): void
    {
        $action->execute($this->website, $this->agent, $this->job->getJobId());
    }

    public function failed(Throwable $e): void
    {
        app(MarkSourceFailedAction::class)->execute($this->website, $this->agent, $e);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':sources:websites:'.$this->website->id;
    }
}
