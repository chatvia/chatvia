<?php

namespace Domain\Knowledge\Ingestion\Actions\Chunks;

use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhancedChunkData;
use Domain\Knowledge\Ingestion\Services\ChunkEmbeddingText;
use Domain\Knowledge\Ingestion\Services\Chunking\Chunker;
use Domain\Knowledge\Ingestion\Services\Enhancement\PageVisualsDescriber;
use Domain\Llm\Operations\Pricing\CostCalculator;

/**
 * Turns a page into a "contextual" chunk before it is stored, for every
 * source type, so the chunk row is written once.
 *
 * The chunker LLM writes a summary of the page (using the neighbouring pages as
 * context) and the questions the page answers. Following Anthropic's
 * contextual-retrieval recipe the summary is prepended to the chunk text that
 * is embedded, which measurably improves recall; the questions get their own
 * dense and BM25 embeddings for question-to-question matching. Document pages
 * also get prose descriptions of their images and tables (see
 * {@see PageVisualsDescriber}), which join the embedded text and the metadata.
 * The embeddings themselves are made by the caller, in one batch.
 */
class EnhanceChunkAction
{
    public function __construct(
        private readonly Chunker $chunker,
        private readonly PageVisualsDescriber $visuals,
        private readonly CostCalculator $costCalculator,
    ) {}

    public function execute(EnhanceChunkData $data): EnhancedChunkData
    {
        $visuals = $data->imageDirectory !== null
            ? $this->visuals->describe($data->page, $data->imageDirectory)
            : null;

        $enhancement = $this->chunker->enhancePage($data->page, $data->previousPage, $data->nextPage);

        $embeddingText = ChunkEmbeddingText::forPage($enhancement->summary, $data->page->content, $visuals?->embeddingText() ?? '');
        $questions = implode("\n", $enhancement->questions);

        return new EnhancedChunkData(
            embeddingText: $embeddingText,
            summary: $enhancement->summary,
            questions: $questions !== '' ? $questions : null,
            metadata: $visuals?->toMetadata(),
            costInCents: $enhancement->cost + $this->embeddingCost($embeddingText) + $this->embeddingCost($questions),
        );
    }

    private function embeddingCost(string $text): float
    {
        if ($text === '') {
            return 0.0;
        }

        return $this->costCalculator->calculateCostInCents(
            $text,
            '',
            (float) config('chatvia.ai.pricing.embedding_per_million_tokens', 0.10),
            0.0,
        );
    }
}
