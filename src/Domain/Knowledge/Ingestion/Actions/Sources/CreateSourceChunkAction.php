<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Llm\Services\TikToken;
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
    /**
     * @return Chunk|null null when a chunk with the same checksum already
     *                    exists for this source (idempotent re-runs).
     */
    public function execute(Agent $agent, Model $source, PageData $page): ?Chunk
    {
        // Cheap fast path: duplicate pages (blank/repeated pages in scanned
        // PDFs) are a common case — skip the embed call entirely.
        if (Chunk::query()
            ->where('chunkable_type', $source->getMorphClass())
            ->where('chunkable_id', $source->getKey())
            ->where('checksum', $page->checksum)
            ->exists()) {
            return null;
        }

        // createOrFirst, not create: per-page batch jobs run concurrently
        // across workers, so two jobs can pass the exists() check with the
        // same checksum — the second insert must absorb the unique violation
        // instead of failing the job (and with it the whole batch).
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
                'tokens' => TikToken::countTokens($page->content),
                'embedding' => embed($page->content, true),
                'embedding_bm25' => Bm25Tokenizer::expression($page->content),
                'metadata' => [],
            ],
        );

        return $chunk->wasRecentlyCreated ? $chunk : null;
    }
}
