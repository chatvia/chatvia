<?php

namespace Domain\Integration\Tool\Actions\Embedding;

use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates the dense (Qwen3) + BM25 (llmlingua2) embeddings of the shared
 * `connector_tools` catalog for one connector_slug — computed ONCE, however
 * many workspaces connect that connector. Custom servers' own tools are
 * embedded by {@see EmbedMcpToolsAction}.
 *
 * Only rows missing either signal are embedded, so the second (and every
 * later) workspace to connect a connector pays nothing: the first connect
 * already embedded the rows.
 */
class EmbedConnectorToolsAction
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function execute(string $connectorSlug): void
    {
        $tools = ConnectorTool::query()
            ->where('connector_slug', $connectorSlug)
            ->where(function ($q): void {
                $q->whereNull('embedding')->orWhereNull('embedding_bm25');
            })
            ->get();

        if ($tools->isEmpty()) {
            return;
        }

        // The connector's name/description give the tool embeddings shared
        // context (so an ambiguously named tool on "Stripe" still surfaces for
        // billing queries). It's identical across workspaces — which is exactly
        // why the catalog row, and its embedding, can be shared.
        $context = $this->connectorContext($connectorSlug);

        foreach ($tools as $tool) {
            try {
                $text = trim($context."\n\n".$tool->name."\n\n".$tool->description);
                $tool->forceFill([
                    'embedding' => embed($text),
                    'embedding_bm25' => Bm25Tokenizer::expression($text),
                ])->save();
            } catch (Throwable $e) {
                Log::warning('Connector tool embedding failed', [
                    'connector_tool_id' => $tool->id,
                    'connector_slug' => $connectorSlug,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * The connector's "{name}\n{description}" context, resolved from the preset
     * catalog (config/connectors.php) or, for builtins, the registry
     * (config/mcp.php). Empty string when neither knows the slug.
     */
    private function connectorContext(string $connectorSlug): string
    {
        $preset = $this->catalog->find($connectorSlug);
        if ($preset !== null) {
            return trim($preset->name."\n".$preset->description);
        }

        foreach ((array) config('mcp.builtin', []) as $entry) {
            if (($entry['slug'] ?? null) === $connectorSlug) {
                return trim((string) ($entry['name'] ?? '')."\n".(string) ($entry['description'] ?? ''));
            }
        }

        return '';
    }
}
