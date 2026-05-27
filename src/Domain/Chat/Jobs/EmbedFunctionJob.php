<?php

namespace Domain\Chat\Jobs;

use Domain\Agents\Models\AgentFunction;
use Domain\Ingestion\Services\Search\Bm25Tokenizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates `functions.embedding` (Qwen3 dense vector) and
 * `functions.embedding_bm25` (BM25 sparse vector via llmlingua2) for a
 * single function. Dispatched by the AgentFunction observer on
 * create/update so user-defined webhook functions are immediately
 * scorable by ToolHybridSearch alongside MCP tools.
 */
class EmbedFunctionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $functionId) {}

    public function handle(): void
    {
        $function = AgentFunction::query()->find($this->functionId);
        if (! $function) {
            return;
        }

        try {
            $text = $function->name."\n\n".$function->description;
            $function->forceFill([
                'embedding' => embed($text),
                'embedding_bm25' => Bm25Tokenizer::expression($text),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Function embedding failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
