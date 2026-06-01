<?php

namespace Domain\Connectors\Actions\Agent;

use Domain\Agents\Data\Resources\Mcp\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Data\AgentConnectorRowData;
use Domain\Connectors\Data\AgentConnectorsResourceData;
use Domain\Connectors\Data\AgentCustomConnectorRowData;
use Domain\Connectors\Services\ConnectorCatalog;
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

        $attachedIds = DB::table('agent_mcp_server')
            ->where('agent_id', $agent->id)
            ->pluck('mcp_server_id')
            ->flip();

        $featured = $this->catalog->all()->values()->map(function ($connector) use ($serverBySlug, $attachedIds): AgentConnectorRowData {
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
            );
        });

        $custom = $servers->whereNull('connector_slug')->values()->map(
            fn (AgentMcpServer $server): AgentCustomConnectorRowData => AgentCustomConnectorRowData::fromModel(
                $server,
                $attachedIds->has($server->id),
            ),
        );

        return new AgentConnectorsResourceData(
            featured: new DataCollection(AgentConnectorRowData::class, $featured),
            custom: new DataCollection(AgentCustomConnectorRowData::class, $custom),
        );
    }
}
