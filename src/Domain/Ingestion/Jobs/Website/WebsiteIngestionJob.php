<?php

namespace Domain\Ingestion\Jobs\Website;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Actions\Website\StartWebsiteIngestionAction;
use Domain\Sources\Web\Events\WebsiteCrawlStarted;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
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

    public function handle(StartWebsiteIngestionAction $action): void
    {
        $action->execute($this->website, $this->agent, $this->job->getJobId());
    }

    public function failed(Throwable $e): void
    {
        $this->website->update(['status' => 'failed']);

        WebsiteCrawlStarted::dispatch($this->website, $this->agent);

        report($e);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':sources:websites:'.$this->website->id;
    }
}
