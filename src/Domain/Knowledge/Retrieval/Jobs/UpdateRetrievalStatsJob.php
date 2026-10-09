<?php

namespace Domain\Knowledge\Retrieval\Jobs;

use Domain\Knowledge\Retrieval\Actions\UpdateRetrievalStatsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Bumps the retrieval counters for the sources behind one knowledge-base
 * search, off the request path.
 */
class UpdateRetrievalStatsJob implements ShouldQueue
{
    use Queueable;

    /**
     * `$results` holds source references only. Jobs queued by an older release
     * carry full search-result rows (objects); the action reads both shapes.
     * The property name stays `results` so those jobs still unserialize.
     *
     * @param  array<int, array{chunkable_type: string, chunkable_id: int|string}|object>  $results
     */
    public function __construct(
        public array $results,
        public int $agentId,
    ) {}

    public function handle(UpdateRetrievalStatsAction $action): void
    {
        $action->execute($this->results, $this->agentId);
    }
}
