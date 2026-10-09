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
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Starts training a website's pending URLs. See
 * {@see StartWebsiteIngestionAction}.
 */
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
