<?php

namespace Domain\Knowledge\Ingestion\Actions\Chunks;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\TokenCounter;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a single text chunk for any source (file, text, media transcript).
 *
 * The chunk-creation pipeline (embed, BM25 tokenize, dedupe by checksum) is
 * identical across DocumentChunkIngestionJob, TextChunkIngestionJob, and
 * MediaChunkIngestionJob. This action consolidates it. Per-source progress
 * events and enhance-job dispatch stay in their respective jobs.
 */
class CreateSourceChunkAction
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
        private readonly TokenCounter $tokens,
    ) {}

    /**
     * @return Chunk|null null when a chunk with the same checksum already
     *                    exists for this source (idempotent re-runs).
     */
    public function execute(Agent $agent, Model $source, PageData $page): ?Chunk
    {
        // Duplicate pages are common (blank/repeated scans) — skip the embed.
        if (Chunk::query()
            ->where('chunkable_type', $source->getMorphClass())
            ->where('chunkable_id', $source->getKey())
            ->where('checksum', $page->checksum)
            ->exists()) {
            return null;
        }

        // createOrFirst: concurrent page jobs can race past the exists() check.
        $chunk = Chunk::query()->createOrFirst(
            [
                'chunkable_type' => $source->getMorphClass(),
                'chunkable_id' => $source->getKey(),
                'checksum' => $page->checksum,
            ],
            [
                'user_id' => $agent->user_id,
                'chunk_index' => $page->pageNumber,
                'chunk' => $page->content,
                'summary' => '',
                'chunked_using_model' => config('chatvia.ai.models.chunker'),
                'cost_in_micro_cents' => 0,
                'tokens' => $this->tokens->count($page->content),
                'embedding' => $this->embeddings->embed($page->content, true),
                'embedding_bm25' => Bm25Tokenizer::expression($page->content),
                'metadata' => [],
            ],
        );

        return $chunk->wasRecentlyCreated ? $chunk : null;
    }
}
