<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use Domain\Integration\Tool\Jobs\ReembedToolVectorsJob;
use Domain\Knowledge\Embedding\Actions\Reindex\StartEmbeddingReindexAction;
use Illuminate\Bus\Batch;

/**
 * Embeds everything stored as vectors again with the embedding model in use:
 * the knowledge (chunks and Q&A questions, one job batch) and every tool.
 * Started when the embedding model changes, and by "Re-index documents" in
 * Settings → AI models. It only queues the work.
 */
final readonly class ReindexEmbeddingsAction
{
    public function __construct(
        private StartEmbeddingReindexAction $startKnowledgeReindex,
    ) {}

    /**
     * @return Batch|null the knowledge batch, null when no knowledge is stored yet
     */
    public function execute(): ?Batch
    {
        ReembedToolVectorsJob::dispatch();

        return $this->startKnowledgeReindex->execute();
    }
}
