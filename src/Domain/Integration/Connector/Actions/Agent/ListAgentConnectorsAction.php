<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Agent\AgentConnectorRowData;
use Domain\Integration\Connector\Data\Agent\AgentConnectorsResourceData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Data\Custom\AgentCustomConnectorRowData;
use Domain\Integration\Connector\Enums\ConnectorRowState;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * Assemble the agent Connectors page payload:
 *   - featured: every catalog entry with its state for this agent
 *     ({@see ConnectorRowState}) and the workspace's server, if connected.
 *   - custom: the workspace's custom MCP servers, each with its per-agent
 *     attachment.
 */
class ListAgentConnectorsAction
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function execute(Agent $agent): AgentConnectorsResourceData
    {
        $servers = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->withCount('agents')
            ->orderBy('created_at')
            ->get();

        $serverBySlug = $servers->whereNotNull('connector_slug')->keyBy('connector_slug');

        // mcp_server_id => always_available, for every server this agent uses.
        $alwaysAvailableById = $agent->mcpServers()->pluck('agent_mcp_server.always_available', 'mcp_servers.id');

        $featured = $this->catalog->connectors()->values()->map(
            fn (ConnectorData $connector): AgentConnectorRowData => $this->featuredRow(
                $connector,
                $serverBySlug->get($connector->slug),
                $alwaysAvailableById,
            ),
        );

        $custom = $servers->whereNull('connector_slug')->values()->map(
            fn (AgentMcpServer $server): AgentCustomConnectorRowData => AgentCustomConnectorRowData::fromModel(
                $server,
                $alwaysAvailableById->has($server->id),
                (bool) $alwaysAvailableById->get($server->id, false),
            ),
        );

        return new AgentConnectorsResourceData(
            featured: new DataCollection(AgentConnectorRowData::class, $featured),
            custom: new DataCollection(AgentCustomConnectorRowData::class, $custom),
        );
    }

    /**
     * @param  Collection<int, mixed>  $alwaysAvailableById
     */
    private function featuredRow(ConnectorData $connector, ?AgentMcpServer $server, Collection $alwaysAvailableById): AgentConnectorRowData
    {
        $attached = $server !== null && $alwaysAvailableById->has($server->id);

        $state = match (true) {
            $attached => ConnectorRowState::Attached,
            $server !== null => ConnectorRowState::Unattached,
            default => ConnectorRowState::Available,
        };

        return new AgentConnectorRowData(
            connector: $connector,
            state: $state,
            server: $server !== null ? AgentMcpServerData::fromModel($server) : null,
            always_available: $attached && (bool) $alwaysAvailableById->get($server->id, false),
        );
    }
}
