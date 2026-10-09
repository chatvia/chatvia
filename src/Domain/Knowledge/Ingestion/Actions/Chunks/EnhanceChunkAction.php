<?php

namespace Domain\Knowledge\Ingestion\Actions\Chunks;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Chunking\PageVisualsData;
use Domain\Knowledge\Ingestion\Services\Chunking\Chunker;
use Domain\Knowledge\Ingestion\Services\Enhancement\PageVisualsDescriber;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Llm\Operations\Pricing\CostCalculator;

/**
 * Upgrades a plain chunk into a "contextual" one, for every source type.
 *
 * The chunker LLM writes a summary of the page (using the neighbouring pages as
 * context) and the questions the page answers. Following Anthropic's
 * contextual-retrieval recipe the summary is prepended to the chunk text before
 * it is embedded again, which measurably improves recall; the questions get
 * their own dense and BM25 embeddings for question-to-question matching.
 * Document pages also get prose descriptions of their images and tables (see
 * {@see PageVisualsDescriber}), which join the embedded text and the metadata.
 *
 * Runs at most once per chunk: an enhanced chunk is skipped, so a retried job
 * does not pay for the LLM calls twice.
 */
class EnhanceChunkAction
{
    public function __construct(
        private readonly Chunker $chunker,
        private readonly PageVisualsDescriber $visuals,
        private readonly CostCalculator $costCalculator,
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(EnhanceChunkData $data): void
    {
        $chunk = $data->chunk;

        if ($chunk->is_enhanced) {
            return;
        }

        $visuals = $data->imageDirectory !== null
            ? $this->visuals->describe($data->page, $data->imageDirectory)
            : null;

        $enhancement = $this->chunker->enhancePage($data->page, $data->previousPage, $data->nextPage);

        $embeddingText = $this->contextualize($enhancement->summary, $data->page->content, $visuals);
        $questions = implode("\n", $enhancement->questions);

        $chunk->update([
            'embedding' => $this->embeddings->embed($embeddingText),
            'embedding_bm25' => Bm25Tokenizer::expression($embeddingText),
            'questions' => $questions !== '' ? $questions : null,
            'questions_embedding' => $questions !== '' ? $this->embeddings->embed($questions, true) : null,
            'questions_embedding_bm25' => $questions !== '' ? Bm25Tokenizer::expression($questions) : null,
            'cost_in_micro_cents' => CostCalculator::centsToMicroCents(
                $enhancement->cost + $this->embeddingCost($embeddingText) + $this->embeddingCost($questions),
            ),
            'summary' => $enhancement->summary,
            'is_enhanced' => true,
            ...($visuals instanceof PageVisualsData ? ['metadata' => $visuals->toMetadata()] : []),
        ]);
    }

    /**
     * The text that is embedded: the summary, then the page, then the image and
     * table descriptions (which describe the same page in prose).
     */
    private function contextualize(string $summary, string $content, ?PageVisualsData $visuals): string
    {
        $visualsText = $visuals?->embeddingText() ?? '';

        if ($visualsText !== '') {
            $content .= "\n\n".$visualsText;
        }

        return $summary !== '' ? $summary."\n\n".$content : $content;
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
