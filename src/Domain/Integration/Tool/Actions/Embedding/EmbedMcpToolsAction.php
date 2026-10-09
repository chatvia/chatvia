<?php

namespace Domain\Integration\Tool\Actions\Embedding;

use Domain\Integration\Mcp\Models\McpTool;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates the dense (Qwen3) + BM25 (llmlingua2) embeddings for a CUSTOM
 * server's per-server `mcp_tools` rows that are missing either signal. The
 * shared preset catalog has its own action, {@see EmbedConnectorToolsAction}.
 *
 * Dispatched out-of-band after a catalog sync so Connect/Refresh returns
 * immediately. Staleness signal: a row is stale when either column is NULL —
 * the sync nulls both when the tool's description changes, forcing a re-embed
 * on the next job run.
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
}
