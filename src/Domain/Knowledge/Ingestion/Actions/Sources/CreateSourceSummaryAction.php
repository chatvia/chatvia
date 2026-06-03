<?php

namespace Domain\Knowledge\Ingestion\Actions\Sources;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Knowledge\Ingestion\Services\TikToken;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Operations\Documents\ContentAnalyzer;
use Domain\Llm\Operations\Documents\Summarizer;
use Domain\Llm\Operations\Pricing\CostCalculator;
use Illuminate\Support\Facades\Storage;

class CreateSourceSummaryAction
{
    public function __construct(
        private readonly ContentAnalyzer $contentAnalyzer,
        private readonly Summarizer $summarizer,
        private readonly CostCalculator $costCalculator,
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(SourceFile|SourceText|SourceWebsite $source): ?Chunk
    {
        if ($source->summary()->exists()) {
            return null;
        }

        // Get signed URL if Mistral file is provided, otherwise use public URL
        $pdfFilepath = $source->toPdf();
        $pdfFileUrl = Storage::temporaryUrl($pdfFilepath, now()->addDay());

        $contentAnalysis = $this->contentAnalyzer->fromUrl($pdfFileUrl);

        $source->update([
            'language' => $contentAnalysis->language,
            'document_classification' => $contentAnalysis->document_type,
        ]);

        $summary = $this->summarizer->fromUrl($pdfFileUrl);

        // Schema guarantees questions is array<string> with 5-7 items.
        $questionsText = implode("\n", $contentAnalysis->questions);

        // Calculate LLM cost for summarization and analysis
        $costInCents = $this->costCalculator->calculateCostInCents(
            inputText: '',
            outputText: prompt('summarization/system').strip_tags($summary),
            pricePerInputMilToken: 2.0,
            pricePerOutputMilToken: 8.0,
        );

        // Add embedding cost (chunk + questions)
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents += $this->costCalculator
            ->calculateCostInCents(strip_tags($summary), '', $embeddingPrice, 0.0);

        if ($questionsText !== '') {
            $costInCents += $this->costCalculator
                ->calculateCostInCents($questionsText, '', $embeddingPrice, 0.0);
        }

        $chunk = Chunk::query()->create([
            'user_id' => $source->user_id,
            'chunkable_type' => get_class($source),
            'chunkable_id' => $source->id,
            'type' => 'text',
            'chunk_index' => 0,
            'chunk' => $summary,
            'summary' => '',
            'questions' => $questionsText !== '' ? $questionsText : null,
            'questions_embedding' => $questionsText !== '' ? embed($questionsText, true) : null,
            'questions_embedding_bm25' => $questionsText !== '' ? Bm25Tokenizer::expression($questionsText) : null,
            'chunked_using_model' => config('ai.models.chunker'),
            'embedding_model' => $this->embeddings->getModelName(),
            'cost_in_micro_cents' => CostCalculator::centsToMicroCents($costInCents),
            'tokens' => TikToken::countTokens($summary),
            'embedding' => embed(strip_tags($summary), true),
            'embedding_bm25' => Bm25Tokenizer::expression(strip_tags($summary)),
            'metadata' => $contentAnalysis->metadata->toArray(),
            'checksum' => hash('xxh128', $summary),
        ]);

        Storage::delete($pdfFilepath);

        return $chunk;
    }
}
