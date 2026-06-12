<?php

namespace Domain\Conversation\Actions\Embeddings;

use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Knowledge\Ingestion\Services\Search\Bm25Tokenizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates the dense (Qwen3) + BM25 (llmlingua2) embeddings for MCP tools
 * missing either signal. Two entry points mirror the two storage shapes:
 *
 *   - {@see execute()} embeds a CUSTOM server's per-server `mcp_tools` rows.
 *   - {@see executeForConnector()} embeds the shared `connector_tools` catalog
 *     for one connector_slug — computed ONCE, regardless of how many workspaces
 *     connect that connector.
 *
 * Both are dispatched out-of-band after a catalog sync so Connect/Refresh
 * returns immediately. Staleness signal: a row is stale when either column is
 * NULL — the sync nulls both when the tool's description changes, forcing a
 * re-embed on the next job run.
 */
class EmbedMcpToolsAction
{
    public function execute(int $mcpServerId): void
    {
        $tools = McpTool::query()
            ->with('mcpServer')
            ->where('mcp_server_id', $mcpServerId)
            ->where(function ($q): void {
                $q->whereNull('embedding')->orWhereNull('embedding_bm25');
            })
            ->get();

        foreach ($tools as $tool) {
            try {
                // Embed the server context alongside the tool so retrieval can
                // match on the connector's name/description too — e.g. an
                // ambiguously named tool on the "Stripe" server still surfaces
                // for billing queries.
                $server = $tool->mcpServer;
                $serverContext = $server ? trim($server->name."\n".(string) $server->description) : '';
                $text = trim($serverContext."\n\n".$tool->name."\n\n".$tool->description);
                $tool->forceFill([
                    'embedding' => embed($text),
                    'embedding_bm25' => Bm25Tokenizer::expression($text),
                ])->save();
            } catch (Throwable $e) {
                Log::warning('MCP tool embedding failed', [
                    'tool_id' => $tool->id,
                    'mcp_server_id' => $tool->mcp_server_id,
                    'error' => $e->getMessage(),
                ]);
                // Leave the row unembedded; the next refresh will try
                // again. Hybrid search degrades gracefully — rows missing
                // an embedding simply don't compete in that retrieval
                // channel, but the BM25 channel can still surface them.
            }
        }
    }

    /**
     * Embed the shared catalog rows for one connector_slug. The NULL-guard means
     * the second (and every later) workspace to connect a connector pays nothing:
     * the rows are already embedded by the first connect.
     */
    public function executeForConnector(string $connectorSlug): void
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
        $preset = app(ConnectorCatalog::class)->find($connectorSlug);
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
