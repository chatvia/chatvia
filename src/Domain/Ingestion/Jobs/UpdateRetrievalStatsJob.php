<?php

namespace Domain\Ingestion\Jobs;

use Domain\Ingestion\Actions\Stats\UpdateRetrievalStatsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateRetrievalStatsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $results,
        public int $agentId,
    ) {}

    public function handle(UpdateRetrievalStatsAction $action): void
    {
        $action->execute($this->results, $this->agentId);
    }
}
