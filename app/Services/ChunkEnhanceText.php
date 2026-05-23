<?php

namespace App\Services;

use App\Data\Ingestion\OCR\PageData;
use App\Models\Chunk;
use App\Services\Infrastructure\LLM\Operations\Chunker;
use App\Services\Infrastructure\LLM\Operations\CostCalculator;
use App\Services\Search\Bm25Tokenizer;

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

        // Build embedding content with summary and questions
        $embeddingContent = $page->content."\n";

        if ($summary) {
            $embeddingContent .= $summary."\n";
        }

        if ($questions) {
            $embeddingContent .= implode("\n", $questions)."\n";
        }

        // Add embedding cost
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents = app(CostCalculator::class)
            ->calculateCostInCents($embeddingContent, '', $embeddingPrice, 0.0) + $enhancedChunk->cost;

        $chunk->update([
            'embedding' => embed($embeddingContent),
            'embedding_bm25' => Bm25Tokenizer::expression($embeddingContent),
            'cost' => $costInCents,
            'summary' => $summary,
            'questions' => $questions,
            'enhanced' => true,
        ]);
    }
}
