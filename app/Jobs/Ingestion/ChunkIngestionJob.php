<?php

namespace App\Jobs\Ingestion;

use App\Contracts\EmbeddingsProviderInterface;
use App\Data\Ingestion\ChunkIngestionData;
use App\Events\FileIngestionProgressEvent;
use App\Events\TextIngestionProgressEvent;
use App\Events\UrlIngestionEvent;
use App\Events\WebsiteIngestionProgressEvent;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\Chunk;
use App\Models\ChunkReference;
use App\Models\SourceFile;
use App\Models\SourceText;
use App\Services\Infrastructure\LLM\Operations\Chunker;
use App\Services\Infrastructure\LLM\Operations\CostCalculator;
use App\Services\Infrastructure\LLM\Operations\Vision;
use App\Services\Prompts\PromptLoader;
use App\Services\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkIngestionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

    public int $backoff = 10;

    public function __construct(
        public ChunkIngestionData $chunk,
        public string $appendUserPrompt = '',
        public array $metadata = [],
        public array $chunkReference = [],
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $systemPrompt = PromptLoader::load('chunking/system');

        $chunkText = $this->chunk->chunk_text;

        $tables = array_values(array_filter(
            array_map('trim', $this->chunk->chunk_tables),
            fn (string $table) => $table !== ''
        ));

        if (Chunk::query()
            ->where('chunkable_type', $this->chunk->chunkable_type)
            ->where('chunkable_id', $this->chunk->chunkable_id)
            ->where('checksum', md5($this->chunk->chunk_text))
            ->exists()) {
            return;
        }

        $agent = Agent::find($this->chunk->agent_id);
        $agentExternalId = $agent->external_id;

        if (! $agent) {
            return;
        }

        $describedImages = [];
        foreach ($this->chunk->chunk_images as $imagePath) {
            // Download image content and convert to data URI to avoid URL access issues
            $imageContent = Storage::get($imagePath);
            $imageDescription = app(Vision::class)->fromDataUrl($imageContent);

            logger()->info(
                'image description: '.$imageDescription.PHP_EOL.'image path: '.$imagePath.PHP_EOL,
            );

            if ($imageDescription !== false) {
                $describedImages[$imagePath] = $imageDescription;
            } else {
                Storage::delete($imagePath);
            }
        }

        $chunkText = Str::of($chunkText)
            ->append(
                implode("\n\n", array_values($describedImages))
            )
            ->append($tables ? "\n\n".implode("\n\n", array_map(
                fn (string $table) => "Table:\n".$table,
                $tables
            )) : '')
            ->toString();

        $userPrompt = PromptLoader::load('chunking/user');
        $userPrompt = template($userPrompt, [
            'text' => $chunkText,
            'previous_context' => $this->chunk->prev_chunk,
            'next_context' => $this->chunk->next_chunk,
        ]).$this->appendUserPrompt;

        $rawChunk = app(Chunker::class)->chunk($userPrompt);

        // Extract summary and questions from XML tags
        $extractedData = $this->extractSummaryAndQuestions($rawChunk);

        $cleanChunk = $extractedData['chunk'];
        $summary = $extractedData['summary'];
        $questions = $extractedData['questions'];

        $tokens = TikToken::countTokens($cleanChunk);

        // Calculate LLM cost for chunking
        $costInCents = app(CostCalculator::class)
            ->calculateCostInCents(
                inputText: $systemPrompt.$userPrompt,
                outputText: $rawChunk,
                pricePerInputMilToken: config('ai.pricing.chunker_input_per_million_tokens'),
                pricePerOutputMilToken: config('ai.pricing.chunker_output_per_million_tokens'),
            );

        $costInCents += app(CostCalculator::class)
            ->calculateCostInCents(
                inputText: $rawChunk,
                pricePerInputMilToken: config('ai.pricing.embedding_output_per_million_tokens'),
                pricePerOutputMilToken: 0.0,
            );

        // Build embedding content with summary and questions
        $embeddingContent = $cleanChunk."\n";

        if ($summary) {
            $embeddingContent .= $summary."\n";
        }

        if ($questions) {
            $embeddingContent .= implode("\n", $questions)."\n";
        }

        $embeddingContent .= collect($describedImages)->pluck('description')->pluck("\n")."\n";
        $embeddingContent .= collect($tables)->implode("\n")."\n";

        // Add embedding cost
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents += app(CostCalculator::class)
            ->calculateCostInCents($embeddingContent, '', $embeddingPrice, 0.0);

        $createdChunk = Chunk::query()->create([
            'user_id' => $agent->user_id,
            'chunkable_type' => $this->chunk->chunkable_type,
            'chunkable_id' => $this->chunk->chunkable_id,
            'type' => 'text',
            'chunk_index' => $this->chunk->chunk_index,
            'chunk' => $cleanChunk,
            'questions' => $questions,
            'summary' => $summary,
            'chunked_using_model' => config('ai.models.chunker'),
            'embedding_model' => app(EmbeddingsProviderInterface::class)->getModelName(),
            'cost_in_cents' => $costInCents,
            'tokens' => $tokens,
            'embedding' => embed($embeddingContent, true),
            'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote($embeddingContent).", 'llmlingua2')"),
            'metadata' => array_merge($this->metadata, [
                'images' => $describedImages,
                'tables' => $tables,
            ]),
            'checksum' => md5($this->chunk->chunk_text),
            'retrieval_count' => 0,
        ]);

        // Create chunk reference if provided
        if ($this->chunkReference) {
            $ref = ChunkReference::create(array_merge($this->chunkReference, [
                'chunk_id' => $createdChunk->id,
            ]));

            // Update status
            if ($ref->referenceable_type === AgentWebsiteUrl::class) {
                /** @var AgentWebsiteUrl $agentWebsiteUrl */
                $agentWebsiteUrl = AgentWebsiteUrl::find($ref->referenceable_id);

                if ($agentWebsiteUrl) {
                    $agentWebsiteUrl->update(['status' => 'ingested', 'crawled_at' => now()]);
                    $agentWebsiteUrl->refresh();

                    // Dispatch events - load relationship fresh to ensure it exists
                    $website = $agentWebsiteUrl->agentWebsite;
                    if ($website) {
                        WebsiteIngestionProgressEvent::dispatch($website, $agentExternalId);
                    }

                    UrlIngestionEvent::dispatch($agentWebsiteUrl, $agentExternalId);
                }
            }
        }

        if ($this->chunk->chunkable_type === SourceFile::class) {
            FileIngestionProgressEvent::dispatch(
                SourceFile::find($this->chunk->chunkable_id),
                $agentExternalId
            );
        }

        if ($this->chunk->chunkable_type === SourceText::class) {
            TextIngestionProgressEvent::dispatch(
                SourceText::find($this->chunk->chunkable_id),
                $agentExternalId
            );
        }
    }

    /**
     * Extract <Chunk>, <Summary>, and <Questions> XML tags and return clean chunk
     */
    private function extractSummaryAndQuestions(string $rawContent): array
    {
        $cleanChunk = null;
        $summary = null;
        $questions = null;

        // Extract <Chunk> tag (the actual chunk content)
        if (preg_match('/<Chunk>(.*?)<\/Chunk>/s', $rawContent, $chunkMatches)) {
            $cleanChunk = trim($chunkMatches[1]);
        }

        // Extract <Summary> tag
        if (preg_match('/<Summary>(.*?)<\/Summary>/s', $rawContent, $summaryMatches)) {
            $summary = trim($summaryMatches[1]);
        }

        // Extract <Questions> tag
        if (preg_match('/<Questions>(.*?)<\/Questions>/s', $rawContent, $questionsMatches)) {
            $questionsText = trim($questionsMatches[1]);
            // Split questions by newlines and filter out empty lines
            $questions = array_values(array_filter(
                array_map('trim', explode("\n", $questionsText)),
                fn ($q) => ! empty($q)
            ));
        }

        // Fallback: if no <Chunk> tag found, use the raw content with tags removed
        if (! $cleanChunk) {
            $cleanChunk = $rawContent;
            $cleanChunk = preg_replace('/<Summary>.*?<\/Summary>\s*/s', '', $cleanChunk);
            $cleanChunk = preg_replace('/<Questions>.*?<\/Questions>\s*/s', '', $cleanChunk);
            $cleanChunk = trim($cleanChunk);
        }

        return [
            'chunk' => $cleanChunk,
            'summary' => $summary,
            'questions' => $questions,
        ];
    }
}
