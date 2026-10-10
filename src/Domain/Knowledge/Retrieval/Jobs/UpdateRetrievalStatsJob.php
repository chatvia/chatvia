<?php

declare(strict_types=1);

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
     * `$results` holds source references only: `{chunkable_type, chunkable_id}`.
     *
     * @param  array<int, array{chunkable_type: string, chunkable_id: int|string}>  $results
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
