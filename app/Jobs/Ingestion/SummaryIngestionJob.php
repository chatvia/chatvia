<?php

namespace App\Jobs\Ingestion;

use App\Contracts\ChunkableContract;
use App\Models\Agent;
use App\Models\Chunk;
use App\Services\Infrastructure\LLM\OpenAI;
use App\Services\Prompts\PromptLoader;
use App\Services\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SummaryIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

    public function __construct(public ChunkableContract $source, public string $content) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if ($this->source->summary()->exists()) {
            return;
        }

        $response = OpenAI::analyzeContent($this->content);

        $this->source->update([
            'language' => $response['language'],
            'document_classification' => $response['document_type'],
        ]);

        $chunk = OpenAI::summarize($this->content);

        if (isset($response['questions'])) {
            $chunk .= PHP_EOL.'<Questions>'.$response['questions'].'</Questions>';
        }

        $costInCents = OpenAI::calculateCostInCents(
            inputText: $this->content,
            outputText: PromptLoader::load('file/describe').strip_tags($chunk),
            pricePerInputMilToken: 2.0,
            pricePerOutputMilToken: 8.0,
        );

        //        $agent = Agent::find($this->source->agent_id);

        Chunk::query()->create([
            'user_id' => $this->source->user_id,
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
            'checksum' => md5($chunk),
            'retrieval_count' => 0,
        ]);
    }

    public function uniqueId()
    {
        return 'ingestion:summary:files:'.$this->source->id;
    }
}
