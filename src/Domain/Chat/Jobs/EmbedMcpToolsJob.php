<?php

namespace Domain\Chat\Jobs;

use Domain\Agents\Models\McpTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates `mcp_tools.embedding` for any rows on a given server that
 * are unembedded or stale. Dispatched by McpCatalog::refresh() after a
 * sync completes; the actual embedding API calls happen out-of-band so
 * the Connect/Refresh request returns immediately.
 *
 * Stale = `embedded_at IS NULL OR embedded_at < updated_at`. The
 * `updated_at` bumps whenever the upsert sees a changed description
 * or input_schema, so this catches both new tools and content changes.
 */
class EmbedMcpToolsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $mcpServerId) {}

    public function handle(): void
    {
        $tools = McpTool::query()
            ->where('mcp_server_id', $this->mcpServerId)
            ->where(function ($q): void {
                $q->whereNull('embedded_at')
                    ->orWhereColumn('embedded_at', '<', 'updated_at');
            })
            ->get();

        foreach ($tools as $tool) {
            try {
                $text = $tool->name."\n\n".$tool->description;
                $tool->forceFill([
                    'embedding' => embed($text),
                    'embedded_at' => now(),
                ])->save();
            } catch (Throwable $e) {
                Log::warning('MCP tool embedding failed', [
                    'tool_id' => $tool->id,
                    'mcp_server_id' => $tool->mcp_server_id,
                    'error' => $e->getMessage(),
                ]);
                // Leave the row unembedded; the next refresh will try
                // again. The router falls back to "inject all active"
                // when scoring fails, so missing embeddings don't break
                // chat — they just disable routing for those tools.
            }
        }
    }
}
