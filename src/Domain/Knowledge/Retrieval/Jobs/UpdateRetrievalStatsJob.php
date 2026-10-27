<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Jobs;

use Domain\Knowledge\Retrieval\Actions\UpdateRetrievalStatsAction;
use Domain\Knowledge\Retrieval\Data\SourceRefData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Bumps the retrieval counters for the sources behind one knowledge-base
 * search, off the request path.
 */
class UpdateRetrievalStatsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, SourceRefData>  $sources
     */
    public function __construct(
        public readonly Collection $sources,
        public readonly int $agentId,
    ) {}

    public function handle(UpdateRetrievalStatsAction $action): void
    {
        $action->execute($this->sources, $this->agentId);
    }
}
