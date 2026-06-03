<?php

namespace Domain\Knowledge\Ingestion\Jobs\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Website\TrainWebsiteUrlAction;
use Domain\Knowledge\Source\Web\Events\UrlIngestionStarted;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class WebsiteUrlTrainJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(public AgentWebsiteUrl $websiteUrl, public Agent $agent)
    {
        $this->onQueue('ingestion.website');
    }

    public function handle(TrainWebsiteUrlAction $action): void
    {
        $action->execute($this->websiteUrl, $this->agent);
    }

    public function failed(Throwable $e): void
    {
        $this->websiteUrl->update(['status' => 'failed']);

        UrlIngestionStarted::dispatch($this->websiteUrl, $this->agent->external_id);

        report($e);
    }

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':websites:url:'.$this->websiteUrl->url.':train:';
    }
}
