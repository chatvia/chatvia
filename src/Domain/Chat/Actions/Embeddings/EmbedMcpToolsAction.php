<?php

namespace Domain\Chat\Actions\Embeddings;

use Domain\Agents\Models\McpTool;
use Domain\Ingestion\Services\Search\Bm25Tokenizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates `mcp_tools.embedding` (Qwen3 dense vector) and
 * `mcp_tools.embedding_bm25` (BM25 sparse vector via llmlingua2) for any
 * rows on a given server that are missing either signal. Dispatched by
 * McpCatalog::refresh() after a sync completes; the actual embedding API
 * calls happen out-of-band so the Connect/Refresh request returns
 * immediately.
 *
 * Staleness signal: a row is considered stale when either column is NULL.
 * The catalog-sync upsert nulls both columns when the tool's name or
 * description changes, which forces re-embedding on the next job run.
 */
class EmbedMcpToolsAction
{
    public function execute(int $mcpServerId): void
    {
        $tools = McpTool::query()
            ->where('mcp_server_id', $mcpServerId)
            ->where(function ($q): void {
                $q->whereNull('embedding')->orWhereNull('embedding_bm25');
            })
            ->get();

        foreach ($tools as $tool) {
            try {
                $text = $tool->name."\n\n".$tool->description;
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
