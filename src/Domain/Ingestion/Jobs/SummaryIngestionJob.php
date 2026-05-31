<?php

namespace Domain\Ingestion\Jobs;

use App\Embeddings\Contracts\EmbeddingsProviderInterface;
use App\LLM\Operations\ContentAnalyzer;
use App\LLM\Operations\CostCalculator;
use App\LLM\Operations\Summarizer;
use Domain\Ingestion\Models\Chunk;
use Domain\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Ingestion\Services\TikToken;
use Domain\Sources\Files\Models\SourceFile;
use Domain\Sources\Text\Models\SourceText;
use Domain\Sources\Web\Models\SourceWebsite;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SummaryIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 1800;

    public function __construct(
        public SourceFile|SourceText|SourceWebsite $source,
    ) {
        $this->onQueue('ingestion.summary');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->source->summary()->exists()) {
            return;
        }

        // Get signed URL if Mistral file is provided, otherwise use public URL
        $pdfFilepath = $this->source->toPdf();
        $pdfFileUrl = Storage::temporaryUrl($pdfFilepath, now()->addDay());

        $contentAnalysis = app(ContentAnalyzer::class)->fromUrl($pdfFileUrl);

        $this->source->update([
            'language' => $contentAnalysis->language,
            'document_classification' => $contentAnalysis->document_type,
        ]);

        $summary = app(Summarizer::class)->fromUrl($pdfFileUrl);

        // Schema guarantees questions is array<string> with 5-7 items.
        $questionsText = implode("\n", $contentAnalysis->questions);

        // Calculate LLM cost for summarization and analysis
        $costInCents = app(CostCalculator::class)->calculateCostInCents(
            inputText: '',
            outputText: prompt('summarization/system').strip_tags($summary),
            pricePerInputMilToken: 2.0,
            pricePerOutputMilToken: 8.0,
        );

        // Add embedding cost (chunk + questions)
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents += app(CostCalculator::class)
            ->calculateCostInCents(strip_tags($summary), '', $embeddingPrice, 0.0);

        if ($questionsText !== '') {
            $costInCents += app(CostCalculator::class)
                ->calculateCostInCents($questionsText, '', $embeddingPrice, 0.0);
        }

        Chunk::query()->create([
            'user_id' => $this->source->user_id,
            'chunkable_type' => get_class($this->source),
            'chunkable_id' => $this->source->id,
            'type' => 'text',
            'chunk_index' => 0,
            'chunk' => $summary,
            'summary' => '',
            'questions' => $questionsText !== '' ? $questionsText : null,
            'questions_embedding' => $questionsText !== '' ? embed($questionsText, true) : null,
            'questions_embedding_bm25' => $questionsText !== '' ? Bm25Tokenizer::expression($questionsText) : null,
            'chunked_using_model' => config('ai.models.chunker'),
            'embedding_model' => app(EmbeddingsProviderInterface::class)->getModelName(),
            'cost_in_micro_cents' => CostCalculator::centsToMicroCents($costInCents),
            'tokens' => TikToken::countTokens($summary),
            'embedding' => embed(strip_tags($summary), true),
            'embedding_bm25' => Bm25Tokenizer::expression(strip_tags($summary)),
            'metadata' => $contentAnalysis->metadata->toArray(),
            'checksum' => hash('xxh128', $summary),
        ]);

        Storage::delete($pdfFilepath);
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }

    public function uniqueId(): string
    {
        return 'ingestion:summary:files:'.$this->source::class.':'.$this->source->id;
    }
}
