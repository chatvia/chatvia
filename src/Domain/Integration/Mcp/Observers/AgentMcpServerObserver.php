<?php

namespace Domain\Integration\Mcp\Observers;

use Domain\Conversation\Jobs\EmbedMcpToolsJob;
use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Support\Facades\DB;

/**
 * MCP servers are now account-level, attached to agents via the
 * `agent_mcp_server` pivot. Cache invalidation must therefore
 * walk that pivot — a single config change can affect any number of agents.
 * Per-server tool catalog used to live in Redis; it's now in the
 * `mcp_tools` table, so server-level changes only need to bust the
 * agent-tool compile cache (server enable/disable affects the pool).
 */
class AgentMcpServerObserver
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function created(AgentMcpServer $server): void
    {
        // No attachments yet on the row itself; Agent::attachConnector busts
        // the per-agent cache when this server is later attached.
    }

    public function updated(AgentMcpServer $server): void
    {
        $this->forgetForAllAttachedAgents($server);

        // Per-server tools embed the server's name + description as context, so
        // renaming or re-describing the server makes every embedding stale.
        // Null them and queue a re-embed so routing matches the new wording.
        if ($server->wasChanged(['name', 'description'])) {
            $stale = McpTool::query()
                ->where('mcp_server_id', $server->id)
                ->update(['embedding' => null, 'embedding_bm25' => null]);

            if ($stale > 0) {
                EmbedMcpToolsJob::dispatch($server->id);
            }
        }
    }

    public function deleted(AgentMcpServer $server): void
    {
        $this->forgetForAllAttachedAgents($server);
    }

    private function forgetForAllAttachedAgents(AgentMcpServer $server): void
    {
        // Get attached agent IDs directly from the pivot table — avoids
        // hydrating Agent models just to read their ids.
        $agentIds = DB::table('agent_mcp_server')
            ->where('mcp_server_id', $server->id)
            ->pluck('agent_id');

        foreach ($agentIds as $agentId) {
            $this->registry->forget((int) $agentId);
        }
    }
}
