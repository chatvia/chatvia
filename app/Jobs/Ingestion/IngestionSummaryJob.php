<?php

namespace App\Jobs\Ingestion;

use App\Models\AgentFile;
use App\Models\Chunk;
use App\Support\OpenAI;
use App\Support\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IngestionSummaryJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public AgentFile $agentFile)
    {
        $this->onQueue('ingestion');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $cacheKey = 'agent:file:openai:summary:'.$this->agentFile->id;

        if (Cache::has($cacheKey)) {
            $response = Cache::get($cacheKey);
        } else {
            $parsedContent = Storage::get($this->agentFile->parser_filepath);

            $content = collect(json_decode($parsedContent, true, 512, JSON_THROW_ON_ERROR))
                ->pluck('text')
                ->join("\n");

            $response = OpenAI::analyzeContent($content);
        }

        $this->agentFile->update([
            'language' => $response['language'],
            'document_classification' => $response['document_type'],
        ]);

        $chunk = '<Summary>'.$response['summary'].'</Summary>';

        if (isset($response['questions'])) {
            $chunk .= PHP_EOL.'<Questions>'.$response['questions'].'</Questions>';
        }

        $costInCents = OpenAI::calculateCostInCents($this->agentFile->url, config('prompts.file-description').$chunk, 2.0, 8.0);

        Chunk::query()->create([
            'agent_id' => $this->agentFile->agent_id,
            'agent_file_id' => $this->agentFile->id,
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

        Cache::delete($cacheKey);
    }
}
