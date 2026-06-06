<?php

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Llm\Operations\Documents\Chunker;
use Domain\Llm\Operations\Pricing\CostCalculator;

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

        $embeddingPrice = config('chatvia.ai.pricing.embedding_per_million_tokens', 0.10);
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
