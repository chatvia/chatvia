<?php

namespace App\Jobs\Ingestion;

use App\Contracts\ChunkableContract;
use App\Contracts\EmbeddingsProviderInterface;
use App\Models\Agent;
use App\Models\Chunk;
use App\Services\Infrastructure\LLM\Operations\ContentAnalyzer;
use App\Services\Infrastructure\LLM\Operations\CostCalculator;
use App\Services\Infrastructure\LLM\Operations\Summarize;
use App\Services\Prompts\PromptLoader;
use App\Services\TikToken;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SummaryIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public ChunkableContract $source, public string $content) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->source->summary()->exists()) {
            return;
        }

        // Skip if content is too short or empty
        $contentLength = str_word_count($this->content);
        if ($contentLength < 50) {
            return;
        }

        try {
            $contentAnalysis = app(ContentAnalyzer::class)->analyze($this->content);
        } catch (Exception $e) {
            // If analysis fails, skip summary generation
            report($e);

            return;
        }

        $this->source->update([
            'language' => $contentAnalysis['language'] ?? null,
            'document_classification' => $contentAnalysis['document_type'] ?? null,
        ]);

        $summary = app(Summarize::class)->summarize($this->content);

        // Extract questions separately
        $questions = null;
        if (isset($contentAnalysis['questions'])) {
            $questions = is_array($contentAnalysis['questions'])
                ? $contentAnalysis['questions']
                : [$contentAnalysis['questions']];
        }

        // Use summary as the chunk content for cleaner embeddings
        $chunkContent = $summary;

        // Calculate LLM cost for summarization and analysis
        $costInCents = app(CostCalculator::class)->calculateCostInCents(
            inputText: $this->content,
            outputText: PromptLoader::load('file/describe').strip_tags($chunkContent),
            pricePerInputMilToken: 2.0,
            pricePerOutputMilToken: 8.0,
        );

        // Add embedding cost
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents += app(CostCalculator::class)
            ->calculateCostInCents(strip_tags($chunkContent), '', $embeddingPrice, 0.0);

        //        $agent = Agent::find($this->source->agent_id);

        Chunk::query()->create([
            'user_id' => $this->source->user_id,
            'chunkable_type' => get_class($this->source),
            'chunkable_id' => $this->source->id,
            'type' => 'text',
            'chunk_index' => 0,
            'chunk' => $chunkContent,
            'questions' => $questions,
            'summary' => $summary,
            'chunked_using_model' => config('ai.models.chunker'),
            'embedding_model' => app(EmbeddingsProviderInterface::class)->getModelName(),
            'cost_in_cents' => $costInCents,
            'tokens' => TikToken::countTokens($chunkContent),
            'embedding' => embed(strip_tags($chunkContent), true),
            'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote(strip_tags($chunkContent)).", 'llmlingua2')"),
            'metadata' => $contentAnalysis['metadata'],
            'checksum' => md5($chunkContent),
            'retrieval_count' => 0,
        ]);
    }

    public function uniqueId()
    {
        return 'ingestion:summary:files:'.$this->source->id;
    }
}
