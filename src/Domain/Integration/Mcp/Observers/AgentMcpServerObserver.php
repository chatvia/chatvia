<?php

namespace Domain\Integration\Mcp\Observers;

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * MCP servers are workspace-level and attach to agents through the
 * `agent_mcp_server` pivot, so one server change can affect any number of
 * agents: every attached agent's cached tool pool is dropped when the server
 * changes or is deleted.
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
        $this->registry->forgetAttachedTo($server);

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

    /**
     * The database removes the pivot rows together with the server (cascade),
     * so the attached agents are read here, before the delete.
     */
    public function deleting(AgentMcpServer $server): void
    {
        $server->load('agents:agents.id');
    }

    public function deleted(AgentMcpServer $server): void
    {
        $this->registry->forgetMany($server->agents->modelKeys());
    }
}
