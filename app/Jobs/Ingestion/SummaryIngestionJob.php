<?php

namespace App\Jobs\Ingestion;

use App\Contracts\EmbeddingsProviderInterface;
use App\Models\Chunk;
use App\Models\SourceFile;
use App\Models\SourceText;
use App\Models\SourceWebsite;
use App\Services\Infrastructure\LLM\Operations\ContentAnalyzer;
use App\Services\Infrastructure\LLM\Operations\CostCalculator;
use App\Services\Infrastructure\LLM\Operations\Summarizer;
use App\Services\Prompts\PromptLoader;
use App\Services\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

class SummaryIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

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

        // Extract questions separately
        $questions = null;
        if ($contentAnalysis->questions) {
            $questions = is_array($contentAnalysis->questions)
                ? $contentAnalysis->questions
                : [$contentAnalysis->questions];
        }

        // Calculate LLM cost for summarization and analysis
        $costInCents = app(CostCalculator::class)->calculateCostInCents(
            inputText: '',
            outputText: PromptLoader::load('file/describe').strip_tags($summary),
            pricePerInputMilToken: 2.0,
            pricePerOutputMilToken: 8.0,
        );

        // Add embedding cost
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents += app(CostCalculator::class)
            ->calculateCostInCents(strip_tags($summary), '', $embeddingPrice, 0.0);

        Chunk::query()->create([
            'user_id' => $this->source->user_id,
            'chunkable_type' => get_class($this->source),
            'chunkable_id' => $this->source->id,
            'type' => 'text',
            'chunk_index' => 0,
            'chunk' => $summary,
            'questions' => $questions,
            'summary' => '',
            'chunked_using_model' => config('ai.models.chunker'),
            'embedding_model' => app(EmbeddingsProviderInterface::class)->getModelName(),
            'cost_in_cents' => $costInCents,
            'tokens' => TikToken::countTokens($summary),
            'embedding' => embed(strip_tags($summary), true),
            'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote(strip_tags($summary)).", 'llmlingua2')"),
            'metadata' => $contentAnalysis->metadata->toArray(),
            'checksum' => hash('xxh128', $summary),
            'retrieval_count' => 0,
        ]);

        Storage::delete($pdfFilepath);
    }

    public function uniqueId()
    {
        return 'ingestion:summary:files:'.$this->source::class.':'.$this->source->id;
    }
}
