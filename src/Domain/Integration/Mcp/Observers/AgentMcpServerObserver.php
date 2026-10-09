<?php

namespace Domain\Integration\Mcp\Observers;

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Support\Facades\DB;

/**
 * MCP servers are account-level and attach to agents through the
 * `agent_mcp_server` pivot, so cache invalidation walks that pivot — one
 * config change can affect any number of agents. The tool catalog lives in
 * the `mcp_tools` table, so server-level changes only need to bust the
 * agent-tool compile cache (enable/disable changes the pool).
 *
 * No `created` hook: a new server has no attachments yet, and
 * `Agent::attachConnector` busts the per-agent cache when it is attached.
 */
class AgentMcpServerObserver
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

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
