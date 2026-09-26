<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Agent\AgentConnectorRowData;
use Domain\Integration\Connector\Data\Agent\AgentConnectorsResourceData;
use Domain\Integration\Connector\Data\Custom\AgentCustomConnectorRowData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

/**
 * Assemble the agent Connectors page payload:
 *   - featured: every catalog entry tagged `attached`/`unattached`/`available`
 *     based on whether the account has the server and whether THIS agent has
 *     it on the `agent_mcp_server` pivot.
 *   - custom: account-level URL-only connectors, each with per-agent
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

        // mcp_server_id => always_available, for every attached server.
        $alwaysAvailableById = DB::table('agent_mcp_server')
            ->where('agent_id', $agent->id)
            ->pluck('always_available', 'mcp_server_id');
        $attachedIds = $alwaysAvailableById->keys()->flip();

        $featured = $this->catalog->connectors()->values()->map(function ($connector) use ($serverBySlug, $attachedIds, $alwaysAvailableById): AgentConnectorRowData {
            /** @var AgentMcpServer|null $row */
            $row = $serverBySlug->get($connector->slug);
            $attached = $row !== null && $attachedIds->has($row->id);

            [$state, $server] = match (true) {
                $attached => ['attached', AgentMcpServerData::fromModel($row)],
                $row !== null => ['unattached', AgentMcpServerData::fromModel($row)],
                default => ['available', null],
            };

            return new AgentConnectorRowData(
                connector: $connector,
                state: $state,
                server: $server,
                always_available: $attached && (bool) $alwaysAvailableById->get($row->id, false),
            );
        });

        $custom = $servers->whereNull('connector_slug')->values()->map(
            fn (AgentMcpServer $server): AgentCustomConnectorRowData => AgentCustomConnectorRowData::fromModel(
                $server,
                $attachedIds->has($server->id),
                (bool) $alwaysAvailableById->get($server->id, false),
            ),
        );

        return new AgentConnectorsResourceData(
            featured: new DataCollection(AgentConnectorRowData::class, $featured),
            custom: new DataCollection(AgentCustomConnectorRowData::class, $custom),
        );
    }
}
