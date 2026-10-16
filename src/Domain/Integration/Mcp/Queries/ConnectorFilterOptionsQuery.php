<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Queries;

use Domain\Integration\Mcp\Data\ConnectorOptionData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Collection;

/**
 * The connector filter options of the audit viewers, from the workspace's
 * configured MCP servers. A deleted connector drops out of the dropdown; its
 * audit rows stay reachable unfiltered.
 */
class ConnectorFilterOptionsQuery
{
    /**
     * @return Collection<int, ConnectorOptionData>
     */
    public function forWorkspace(int $workspaceId): Collection
    {
        return AgentMcpServer::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('connector_slug')
            ->select('connector_slug', 'name')
            ->orderBy('name')
            ->get()
            ->unique('connector_slug')
            ->map(fn (AgentMcpServer $server): ConnectorOptionData => new ConnectorOptionData(
                slug: (string) $server->connector_slug,
                name: $server->name,
            ))
            ->values();
    }
}
