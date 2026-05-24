<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Chat\Services\Tooling\ToolRegistry;

class AgentMcpServerObserver
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly McpCatalog $catalog,
    ) {}

    public function created(AgentMcpServer $server): void
    {
        $server->agent()->increment('mcp_servers_count');
        $this->registry->forget($server->agent_id);
    }

    public function updated(AgentMcpServer $server): void
    {
        // Catalog is keyed on server id, registry is keyed on agent id —
        // bust both so url/auth changes are visible immediately.
        $this->catalog->forget($server);
        $this->registry->forget($server->agent_id);
    }

    public function deleted(AgentMcpServer $server): void
    {
        $server->agent()->where('mcp_servers_count', '>', 0)->decrement('mcp_servers_count');
        $this->catalog->forget($server);
        $this->registry->forget($server->agent_id);
    }
}
