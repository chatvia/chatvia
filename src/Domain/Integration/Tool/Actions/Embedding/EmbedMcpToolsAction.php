<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Actions\Embedding;

use Domain\Integration\Mcp\Models\McpTool;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingServiceMissingException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Populates the dense embedding + lexical search text for a CUSTOM
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
    public function __construct(
        private readonly EmbeddingsProvider $embeddings,
    ) {}

    public function execute(int $mcpServerId): void
    {
        $tools = McpTool::query()
            ->with('mcpServer')
            ->where('mcp_server_id', $mcpServerId)
            ->where(function ($q): void {
                $q->whereNull('embedding')->orWhereNull('search_text');
            })
            ->get();

        foreach ($tools as $tool) {
            try {
                // Embed the server context alongside the tool so retrieval can
                // match on the connector's name/description too — e.g. an
                // ambiguously named tool on the "Stripe" server still surfaces
                // for billing queries.
                $server = $tool->mcpServer;
                $serverContext = $server ? Str::trim($server->name."\n".$server->description) : '';
                $text = Str::trim($serverContext."\n\n".$tool->name."\n\n".$tool->description);
                $tool->forceFill([
                    'embedding' => $this->embeddings->embed($text),
                    'search_text' => $text,
                ])->save();
            } catch (EmbeddingServiceMissingException) {
                // No service is picked yet: every row would fail the same
                // way. The next refresh tries again.
                return;
            } catch (Throwable $e) {
                Log::warning('MCP tool embedding failed', [
                    'tool_id' => $tool->id,
                    'mcp_server_id' => $tool->mcp_server_id,
                    'error' => $e->getMessage(),
                ]);
                // Leave the row unembedded; the next refresh will try
                // again. Hybrid search degrades gracefully — rows missing
                // an embedding simply don't compete in that retrieval
                // channel, but the keyword channel can still surface them.
            }
        }
    }
}
