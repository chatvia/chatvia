<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\ToolRegistry;
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
        // No attachments yet on the row itself; the pivot observer handles
        // per-agent cache busting when this server is later attached.
    }

    public function updated(AgentMcpServer $server): void
    {
        $this->forgetForAllAttachedAgents($server);
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
