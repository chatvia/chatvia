<?php

namespace Domain\Ingestion\Services;

use App\LLM\Operations\Chunker;
use App\LLM\Operations\CostCalculator;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Models\Chunk;
use Domain\Ingestion\Models\ChunkQuestion;
use Domain\Ingestion\Services\Search\Bm25Tokenizer;

class ChunkEnhanceText
{
    public function enhanceText(Chunk $chunk, PageData $page, ?PageData $previousPage = null, ?PageData $nextPage = null): void
    {
        if ($chunk->enhanced) {
            return;
        }

        $enhancedChunk = app(Chunker::class)->chunkDocument(
            page: $page,
            prevPage: $previousPage,
            nextPage: $nextPage,
        );

        $summary = $enhancedChunk->summary;
        $questions = $enhancedChunk->questions;

        // Embed only the chunk's own content. Summary and questions live in
        // dedicated columns / chunk_questions rows so the chunk vector stays
        // a clean representation of the source text.
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents = app(CostCalculator::class)
            ->calculateCostInCents($page->content, '', $embeddingPrice, 0.0) + $enhancedChunk->cost;

        foreach ($questions as $question) {
            $costInCents += app(CostCalculator::class)
                ->calculateCostInCents($question, '', $embeddingPrice, 0.0);
        }

        $chunk->update([
            'embedding' => embed($page->content),
            'embedding_bm25' => Bm25Tokenizer::expression($page->content),
            'cost_in_cents' => $costInCents,
            'summary' => $summary,
            'enhanced' => true,
        ]);

        ChunkQuestion::query()->where('chunk_id', $chunk->id)->delete();

        foreach ($questions as $question) {
            ChunkQuestion::query()->create([
                'chunk_id' => $chunk->id,
                'question' => $question,
                'embedding' => embed($question, true),
            ]);
        }
    }
}
