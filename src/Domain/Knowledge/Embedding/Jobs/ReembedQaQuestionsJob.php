<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Jobs;

use Domain\Knowledge\Embedding\Actions\Reindex\ReembedQaQuestionsAction;
use Domain\Knowledge\Embedding\Actions\Reindex\StartEmbeddingReindexAction;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/**
 * One part of a re-index ({@see StartEmbeddingReindexAction}): embeds the
 * Q&A questions in an id range again. Retried like {@see ReembedChunksJob}.
 */
// Rides the chunk-embedding supervisor a re-index competes with anyway.
#[Queue('ingestion.chunks')]
// Each range is idempotent, so retry generously through provider hiccups.
#[Tries(5)]
// Longer and longer waits, so a rate-limited embedding service can recover.
#[Backoff(10, 60, 300, 900)]
class ReembedQaQuestionsJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public readonly int $firstId,
        public readonly int $lastId,
    ) {}

    public function handle(ReembedQaQuestionsAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->firstId, $this->lastId);
    }
}
