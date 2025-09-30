<?php

namespace App\Jobs\Ingestion;

use App\Contracts\ChunkableContract;
use App\Models\Chunk;
use App\Support\OpenAI;
use App\Support\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IngestionSummaryJob implements ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

    public function __construct(public ChunkableContract $source, public string $content)
    {
        $this->onQueue('ingestion');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $response = OpenAI::analyzeContent($this->content);

        $this->source->update([
            'language' => $response['language'],
            'document_classification' => $response['document_type'],
        ]);

        $chunk = '<Summary>'.$response['summary'].'</Summary>';

        if (isset($response['questions'])) {
            $chunk .= PHP_EOL.'<Questions>'.$response['questions'].'</Questions>';
        }

        $costInCents = OpenAI::calculateCostInCents(
            inputText: $this->content,
            outputText: config('prompts.file-description').strip_tags($chunk),
            pricePerInputMilToken: 2.0,
            pricePerOutputMilToken: 8.0,
        );

        Chunk::query()->create([
            'agent_id' => $this->source->agent_id,
            'chunkable_type' => get_class($this->source),
            'chunkable_id' => $this->source->id,
            'type' => 'text',
            'chunk_index' => 0,
            'chunk' => $chunk,
            'chunked_using_model' => 'gpt-5-mini',
            'cost_in_cents' => $costInCents,
            'tokens' => TikToken::countTokens($chunk),
            'embedding' => embed(strip_tags($chunk), true),
            'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote(strip_tags($chunk)).", 'llmlingua2')"),
            'metadata' => $response['metadata'],
            'md5_hash' => md5($chunk),
            'retrieval_count' => 0,
        ]);
    }
}
