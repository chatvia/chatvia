<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Agent\AgentCapabilityRowData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgentCapabilitiesController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $serverIdBySlug = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->whereIn('connector_slug', $this->catalog->capabilities()->keys())
            ->pluck('id', 'connector_slug');

        $attachedIds = DB::table('agent_mcp_server')
            ->where('agent_id', $agent->id)
            ->pluck('mcp_server_id')
            ->flip();

        $capabilities = $this->catalog->capabilities()->values()->map(
            fn (ConnectorData $capability): AgentCapabilityRowData => new AgentCapabilityRowData(
                connector: $capability,
                enabled: ($id = $serverIdBySlug->get($capability->slug)) !== null && $attachedIds->has($id),
            ),
        );

        return Inertia::render('agents/Capabilities/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'capabilities' => AgentCapabilityRowData::collect($capabilities),
        ]);
    }
}
