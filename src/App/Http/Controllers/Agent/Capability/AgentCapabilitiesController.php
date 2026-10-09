<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Agent\AgentCapabilityRowData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Capabilities page: every first-party capability
 * (Interactive Charts, Forms, …) and whether this agent has it on.
 */
class AgentCapabilitiesController
{
    public function __invoke(Agent $agent, ConnectorCatalog $catalog): Response
    {
        $capabilities = $catalog->capabilities();

        // Capability slug => the workspace's server id, for those connected.
        $serverIdBySlug = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->whereIn('connector_slug', $capabilities->keys())
            ->pluck('id', 'connector_slug');

        $attachedIds = $agent->mcpServers()->pluck('mcp_servers.id')->flip();

        $rows = $capabilities->values()->map(
            fn (ConnectorData $capability): AgentCapabilityRowData => new AgentCapabilityRowData(
                connector: $capability,
                enabled: ($serverId = $serverIdBySlug->get($capability->slug)) !== null && $attachedIds->has($serverId),
            ),
        );

        return Inertia::render('agents/Capabilities/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'capabilities' => AgentCapabilityRowData::collect($rows),
        ]);
    }
}
