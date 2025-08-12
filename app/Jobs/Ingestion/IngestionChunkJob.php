<?php

namespace App\Jobs\Ingestion;

use App\Data\Ingestion\ChunkIngestionData;
use App\Models\Chunk;
use App\Support\OpenAI;
use App\Support\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IngestionChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public ChunkIngestionData $chunk)
    {
        $this->onQueue('ingestion');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $systemPrompt = config('prompts.chunking-system');

        $visionPrompt = config('prompts.image-description');

        $chunkText = $this->chunk->chunk_text;

        $describedImages = [];

        if (Chunk::query()->where('agent_file_id', $this->chunk->agent_file_id)->where('md5_hash', md5($this->chunk->chunk_text))->exists()) {
            return;
        }

        $userPrompt = config('prompts.chunking-user');

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
        ]);

        $chunk = OpenAI::completion([
            'model' => 'gpt-4.1-mini',
            'temperature' => 0.0,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ]);

        $tokens = TikToken::countTokens($chunk);

        $costInCents = OpenAI::calculateCostInCents($systemPrompt.$userPrompt, $chunk);

        Chunk::query()->create([
            'agent_id' => $this->chunk->agent_id,
            'agent_file_id' => $this->chunk->agent_file_id,
            'type' => 'text',
            'chunk_index' => $this->chunk->chunk_index,
            'chunk' => $chunk,
            'chunked_using_model' => 'gpt-4.1-mini',
            'cost_in_cents' => $costInCents,
            'tokens' => $tokens,
            'embedding' => embed($chunk, true),
            'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote($chunk).", 'llmlingua2')"),
            'metadata' => [
                'images' => $describedImages,
            ],
            'md5_hash' => md5($this->chunk->chunk_text),
            'retrieval_count' => 0,
        ]);
    }
}
