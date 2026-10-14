<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Jobs;

use Domain\Knowledge\Retrieval\Actions\RebuildVectorIndexAction;
use Domain\Knowledge\Retrieval\Enums\VectorIndexRebuild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Builds the vector indexes on chunks again when they need it, off the
 * request path, as `vector:reindex` does from the console. Queued once a
 * re-index of the stored vectors is done.
 *
 * One try: a failed build leaves the live index in place, and the next build
 * drops what it left behind. The timeout stays below `redis-long`'s
 * retry_after (1900 s), as a large table takes long to build.
 */
#[Queue('ingestion.chunks')]
#[Tries(1)]
#[Timeout(1800)]
class RebuildVectorIndexesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public VectorIndexRebuild $when) {}

    public function handle(RebuildVectorIndexAction $rebuild): void
    {
        foreach (array_keys(RebuildVectorIndexAction::INDEXES) as $index) {
            $rebuild->execute($index, $this->when);
        }
    }
}
