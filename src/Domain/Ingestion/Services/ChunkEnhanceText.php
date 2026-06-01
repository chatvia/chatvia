<?php

namespace Domain\Ingestion\Services;

use App\LLM\Operations\Documents\Chunker;
use App\LLM\Operations\Pricing\CostCalculator;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Models\Chunk;
use Domain\Ingestion\Services\Search\Bm25Tokenizer;

class ChunkEnhanceText
{
    public function enhanceText(Chunk $chunk, PageData $page, ?PageData $previousPage = null, ?PageData $nextPage = null): void
    {
        if ($chunk->is_enhanced) {
            return;
        }

        $enhancedChunk = app(Chunker::class)->chunkDocument(
            page: $page,
            prevPage: $previousPage,
            nextPage: $nextPage,
        );

        $summary = $enhancedChunk->summary;
        $questions = $enhancedChunk->questions;
        $questionsText = implode("\n", $questions);

        // Contextual Retrieval (per Anthropic cookbook): prepend the LLM-generated
        // summary to the chunk text before embedding. The summary situates the
        // chunk in its document context, which lifts Pass@10 by ~5pp in the
        // cookbook's evaluation. Questions still live in their own column for
        // independent HyDE-style matching.
        $contextualizedChunk = $summary !== ''
            ? $summary."\n\n".$page->content
            : $page->content;

        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents = app(CostCalculator::class)
            ->calculateCostInCents($contextualizedChunk, '', $embeddingPrice, 0.0) + $enhancedChunk->cost;

        if ($questionsText !== '') {
            $costInCents += app(CostCalculator::class)
                ->calculateCostInCents($questionsText, '', $embeddingPrice, 0.0);
        }

        $chunk->update([
            'embedding' => embed($contextualizedChunk),
            'embedding_bm25' => Bm25Tokenizer::expression($contextualizedChunk),
            'questions' => $questionsText !== '' ? $questionsText : null,
            'questions_embedding' => $questionsText !== '' ? embed($questionsText, true) : null,
            'questions_embedding_bm25' => $questionsText !== '' ? Bm25Tokenizer::expression($questionsText) : null,
            'cost_in_micro_cents' => CostCalculator::centsToMicroCents($costInCents),
            'summary' => $summary,
            'is_enhanced' => true,
        ]);
    }
}
