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
#[Queue('ingestion.chunks')]
#[Tries(5)]
#[Backoff(10, 60, 300, 900)]
class ReembedQaQuestionsJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public int $firstId,
        public int $lastId,
    ) {}

    public function handle(ReembedQaQuestionsAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute($this->firstId, $this->lastId);
    }
}
