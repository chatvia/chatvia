<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Knowledge\Ingestion\Data\Analysis\AnalyzedDocumentData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ContentAnalyzer;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Data\Chat\ChatResponseData;
use Domain\Llm\Operations\Documents\Summarizer;
use Domain\Llm\Operations\Pricing\CostCalculator;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Services\TikToken;
use Illuminate\Support\Facades\Storage;

/**
 * Adds the summary chunk (chunk_index 0) of a source: one LLM call classifies
 * the whole document and one summarises it. Skipped when no LLM that can read
 * PDF documents is configured — the source is still searchable by its pages.
 */
class CreateSourceSummaryAction
{
    public function __construct(
        private readonly ContentAnalyzer $contentAnalyzer,
        private readonly Summarizer $summarizer,
        private readonly CostCalculator $costCalculator,
        private readonly DocumentLlm $documentLlm,
    ) {}

    public function execute(SourceFile|SourceText|SourceWebsite $source): ?Chunk
    {
        if (! $this->documentLlm->isAvailable() || $source->summary()->exists()) {
            return null;
        }

        [$analyzed, $summary] = $this->readDocument($source);

        $summaryText = $summary->content;
        $plainSummary = strip_tags($summaryText);
        $questions = implode("\n", $analyzed->analysis->questions);

        return Chunk::query()->create([
            'user_id' => $source->user_id,
            'chunkable_type' => $source->getMorphClass(),
            'chunkable_id' => $source->id,
            'chunk_index' => 0,
            'chunk' => $summaryText,
            'summary' => '',
            'questions' => $questions !== '' ? $questions : null,
            'questions_embedding' => $questions !== '' ? embed($questions, true) : null,
            'questions_embedding_bm25' => $questions !== '' ? Bm25Tokenizer::expression($questions) : null,
            'chunked_using_model' => config('chatvia.ai.models.chunker'),
            'cost_in_micro_cents' => CostCalculator::centsToMicroCents(
                $this->llmCost($analyzed, $summary) + $this->embeddingCost($plainSummary) + $this->embeddingCost($questions),
            ),
            'tokens' => TikToken::countTokens($summaryText),
            'embedding' => embed($plainSummary, true),
            'embedding_bm25' => Bm25Tokenizer::expression($plainSummary),
            'metadata' => $analyzed->analysis->metadata->toArray(),
            'checksum' => hash('xxh128', $summaryText),
        ]);
    }

    /**
     * Classify and summarise the document. The model reads it as a PDF from a
     * signed URL; withPdf() deletes a temporary converted PDF once both calls
     * are done, and never touches a stored original PDF. The detected language
     * and type are saved on the source.
     *
     * @return array{0: AnalyzedDocumentData, 1: ChatResponseData}
     */
    private function readDocument(SourceFile|SourceText|SourceWebsite $source): array
    {
        return $source->withPdf(function (string $pdfPath) use ($source): array {
            $pdfUrl = Storage::temporaryUrl($pdfPath, now()->addDay());

            $analyzed = $this->contentAnalyzer->analyze($pdfUrl);

            $source->update([
                'language' => $analyzed->analysis->language,
                'document_classification' => $analyzed->analysis->document_type,
            ]);

            return [$analyzed, $this->summarizer->summarize($pdfUrl)];
        });
    }

    /**
     * Both calls are priced from the token counts the provider reports (the
     * prompt holds the whole document).
     */
    private function llmCost(AnalyzedDocumentData $analyzed, ChatResponseData $summary): float
    {
        $inputPrice = (float) config('chatvia.ai.pricing.summary_input_per_million_tokens', 2.0);
        $outputPrice = (float) config('chatvia.ai.pricing.summary_output_per_million_tokens', 8.0);

        return $this->costCalculator->costInCentsFromUsage($analyzed->usage, $inputPrice, $outputPrice)
            + $this->costCalculator->costInCentsFromUsage($summary->usage, $inputPrice, $outputPrice);
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
