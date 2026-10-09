<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Container\Attributes\Scoped;

/**
 * Finds the workspace MCP server behind an `mcp__{slug}__{tool}` name, once
 * per request (or queued job). Every MCP tool call needs the same server row
 * three times — for the display label, the approval check and the dispatch —
 * and the tool loop can call the same server on several iterations. Bound as a
 * scoped instance so all of those share one lookup instead of each running its
 * own query.
 *
 * Servers are unique per (workspace, slug), so the workspace lookup and the
 * "attached to this agent" lookup always agree on which row they mean.
 */
#[Scoped]
class McpServerLookup
{
    /** @var array<string, AgentMcpServer|null> */
    private array $byWorkspace = [];

    /** @var array<string, AgentMcpServer|null> */
    private array $attached = [];

    /**
     * The workspace's server with this slug, enabled or not, attached or not.
     */
    public function forWorkspace(int $workspaceId, string $slug): ?AgentMcpServer
    {
        $key = $workspaceId.'|'.$slug;

        if (! array_key_exists($key, $this->byWorkspace)) {
            $this->byWorkspace[$key] = AgentMcpServer::query()
                ->where('user_id', $workspaceId)
                ->where('slug', $slug)
                ->first();
        }

        return $this->byWorkspace[$key];
    }

    /**
     * The server only when it is enabled and attached to this agent — the rule
     * a tool call must pass before it is dispatched.
     */
    public function attachedToAgent(Agent $agent, string $slug): ?AgentMcpServer
    {
        $key = $agent->id.'|'.$slug;

        if (! array_key_exists($key, $this->attached)) {
            $server = $this->forWorkspace((int) $agent->user_id, $slug);

            $this->attached[$key] = $server instanceof AgentMcpServer
                && $server->is_enabled
                // Pivot-only probe: the server row is already loaded.
                && $agent->mcpServers()->newPivotQuery()->where('mcp_server_id', $server->id)->exists()
                ? $server
                : null;
        }

        return $this->attached[$key];
    }
}
