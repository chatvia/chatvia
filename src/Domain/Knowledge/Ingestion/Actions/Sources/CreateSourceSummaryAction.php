<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ContentAnalyzer;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Operations\Documents\Summarizer;
use Domain\Llm\Operations\Pricing\CostCalculator;
use Domain\Llm\Services\TikToken;
use Illuminate\Support\Facades\Storage;

class CreateSourceSummaryAction
{
    public function __construct(
        private readonly ContentAnalyzer $contentAnalyzer,
        private readonly Summarizer $summarizer,
        private readonly CostCalculator $costCalculator,
    ) {}

    public function execute(SourceFile|SourceText|SourceWebsite $source): ?Chunk
    {
        if ($source->summary()->exists()) {
            return null;
        }

        // The model reads the document as a PDF from a signed URL. withPdf()
        // deletes a temporary converted PDF once both calls are done, and never
        // touches a stored original PDF.
        [$analysisResponse, $contentAnalysis, $summaryResponse] = $source->withPdf(function (string $pdfPath) use ($source): array {
            $pdfFileUrl = Storage::temporaryUrl($pdfPath, now()->addDay());

            $analysisResponse = $this->contentAnalyzer->complete($pdfFileUrl);
            $contentAnalysis = $this->contentAnalyzer->parse($analysisResponse);

            $source->update([
                'language' => $contentAnalysis->language,
                'document_classification' => $contentAnalysis->document_type,
            ]);

            return [$analysisResponse, $contentAnalysis, $this->summarizer->complete($pdfFileUrl)];
        });

        $summary = (string) $summaryResponse->json('choices.0.message.content');

        // Schema guarantees questions is array<string> with 5-7 items.
        $questionsText = implode("\n", $contentAnalysis->questions);

        // LLM cost of the analysis and summary calls, from the token counts the
        // provider reports (the prompt includes the whole document).
        $summaryInputPrice = (float) config('chatvia.ai.pricing.summary_input_per_million_tokens', 2.0);
        $summaryOutputPrice = (float) config('chatvia.ai.pricing.summary_output_per_million_tokens', 8.0);

        $costInCents = $this->costCalculator->costInCentsFromUsage($analysisResponse->json('usage'), $summaryInputPrice, $summaryOutputPrice)
            + $this->costCalculator->costInCentsFromUsage($summaryResponse->json('usage'), $summaryInputPrice, $summaryOutputPrice);

        // Add embedding cost (chunk + questions)
        $embeddingPrice = (float) config('chatvia.ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents += $this->costCalculator
            ->calculateCostInCents(strip_tags($summary), '', $embeddingPrice, 0.0);

        if ($questionsText !== '') {
            $costInCents += $this->costCalculator
                ->calculateCostInCents($questionsText, '', $embeddingPrice, 0.0);
        }

        $chunk = Chunk::query()->create([
            'user_id' => $source->user_id,
            'chunkable_type' => $source->getMorphClass(),
            'chunkable_id' => $source->id,
            'chunk_index' => 0,
            'chunk' => $summary,
            'summary' => '',
            'questions' => $questionsText !== '' ? $questionsText : null,
            'questions_embedding' => $questionsText !== '' ? embed($questionsText, true) : null,
            'questions_embedding_bm25' => $questionsText !== '' ? Bm25Tokenizer::expression($questionsText) : null,
            'chunked_using_model' => config('chatvia.ai.models.chunker'),
            'cost_in_micro_cents' => CostCalculator::centsToMicroCents($costInCents),
            'tokens' => TikToken::countTokens($summary),
            'embedding' => embed(strip_tags($summary), true),
            'embedding_bm25' => Bm25Tokenizer::expression(strip_tags($summary)),
            'metadata' => $contentAnalysis->metadata->toArray(),
            'checksum' => hash('xxh128', $summary),
        ]);

        return $chunk;
    }
}
