<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\ChunkIngestionData;
use App\Events\UrlIngestionEvent;
use App\Models\AgentWebsiteUrl;
use App\Models\Chunk;
use App\Models\ChunkReference;
use App\Services\Infrastructure\LLM\OpenAI;
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

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public ChunkIngestionData $chunk,
        public string $appendUserPrompt = '',
        public array $metadata = [],
        public array $chunkReference = [],
    ) {
        // Queue is inherited from parent job via batch
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $systemPrompt = PromptLoader::load('chunking/system');

        $visionPrompt = PromptLoader::load('image/describe');

        $chunkText = $this->chunk->chunk_text;

        $describedImages = [];

        if (Chunk::query()
            ->where('chunkable_type', $this->chunk->chunkable_type)
            ->where('chunkable_id', $this->chunk->chunkable_id)
            ->where('checksum', md5($this->chunk->chunk_text))
            ->exists()) {
            return;
        }

        $userPrompt = PromptLoader::load('chunking/user');

        foreach ($this->chunk->chunk_images as $imagePath) {
            $description = OpenAI::vision(
                prompt: $visionPrompt,
                image: Storage::url($imagePath),
            );

            if ($description !== '0') {
                $describedImages[$imagePath] = $description;
                $this->chunk->chunk_text .= $this->chunk->chunk_text."\n\n".$description;
            }
        }

        $chunkText = Str::of($chunkText)
            ->append(
                implode("\n\n", array_values($describedImages))
            )
            ->toString();

        $userPrompt = template($userPrompt, [
            'text' => $chunkText,
            'previous_context' => $this->chunk->prev_chunk,
            'next_context' => $this->chunk->next_chunk,
        ]).$this->appendUserPrompt;

        $chunk = OpenAI::chunk($userPrompt);

        $tokens = TikToken::countTokens($chunk);

        $costInCents = OpenAI::calculateCostInCents($systemPrompt.$userPrompt, $chunk);

        $createdChunk = Chunk::query()->create([
            'agent_id' => $this->chunk->agent_id,
            'chunkable_type' => $this->chunk->chunkable_type,
            'chunkable_id' => $this->chunk->chunkable_id,
            'type' => 'text',
            'chunk_index' => $this->chunk->chunk_index,
            'chunk' => $chunk,
            'chunked_using_model' => 'gpt-4.1-mini',
            'cost_in_cents' => $costInCents,
            'tokens' => $tokens,
            'embedding' => embed($chunk, true),
            'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote($chunk).", 'llmlingua2')"),
            'metadata' => array_merge($this->metadata, [
                'images' => $describedImages,
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
                $url = AgentWebsiteUrl::find($ref->referenceable_id);
                $url->update(['status' => 'ingested', 'crawled_at' => now()]);

                UrlIngestionEvent::dispatch($url);
            }
        }
    }
}
