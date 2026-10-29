<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Agent\AgentCapabilityRowData;
use Domain\Integration\Connector\Data\Agent\CapabilityProviderData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\CapabilityProvider;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Capabilities page: every first-party capability
 * (Interactive Charts, Forms, …), whether this agent has it on, and
 * which connector it works through (if any) is connected and in use.
 */
class AgentCapabilitiesController
{
    public function __invoke(Agent $agent, ConnectorCatalog $catalog, CapabilityProvider $providers): Response
    {
        $capabilities = $catalog->capabilities();

        // Capability slug => the workspace's server id, for those connected.
        $serverIdBySlug = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->whereIn('connector_slug', $capabilities->keys())
            ->pluck('id', 'connector_slug');

        $attachedIds = $agent->mcpServers()->pluck('mcp_servers.id')->flip();

        $rows = $capabilities->values()->map(function (ConnectorData $capability) use ($agent, $catalog, $providers, $serverIdBySlug, $attachedIds): AgentCapabilityRowData {
            $connected = $providers->connected($agent, $capability);

            return new AgentCapabilityRowData(
                connector: $capability,
                enabled: ($serverId = $serverIdBySlug->get($capability->slug)) !== null && $attachedIds->has($serverId),
                ready: $providers->isMet($agent, $capability),
                providers: collect($capability->worksThrough)
                    ->map(fn (string $slug): ?ConnectorData => $catalog->find($slug))
                    ->filter()
                    ->map(fn (ConnectorData $connector): CapabilityProviderData => new CapabilityProviderData($connector, collect($connected)->containsStrict($connector->slug)))
                    ->values()
                    ->all(),
                provider: $providers->providerFor($agent, $capability),
            );
        });

        return Inertia::render('dashboard/agents/pages/Capabilities/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'capabilities' => AgentCapabilityRowData::collect($rows),
        ]);
    }
}
