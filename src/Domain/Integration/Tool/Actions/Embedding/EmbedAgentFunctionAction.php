<?php

namespace Domain\Integration\Tool\Actions\Embedding;

use Domain\Integration\Function\Models\AgentFunction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Populates `functions.embedding` (dense vector) and
 * `functions.search_text` (what the lexical channel searches) for a
 * single function. Dispatched by the AgentFunction observer on
 * create/update so user-defined webhook functions are immediately
 * scorable by ToolHybridSearch alongside MCP tools.
 *
 * A failed embedding is logged and leaves the row unembedded; the hybrid
 * search simply skips it in the missing channel.
 */
class EmbedAgentFunctionAction
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    public function execute(int $functionId): void
    {
        $function = AgentFunction::query()->find($functionId);
        if (! $function) {
            return;
        }

        try {
            // Include the display name alongside the internal name + description
            // so retrieval matches on the user-facing label too.
            $name = Str::trim(($function->display_name ?? '')."\n".$function->internal_name);
            $text = Str::trim($name."\n\n".$function->description);
            $function->forceFill([
                'embedding' => $this->embeddings->embed($text),
                'search_text' => $text,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Function embedding failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
