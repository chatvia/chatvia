<?php

namespace App\Http\Controllers\Agent\Capability;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisableAgentCapabilityController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Agent $agent, string $slug): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $capability = $this->catalog->capabilities()->get($slug);
        abort_unless($capability !== null, 404, 'Capability not found.');

        // Disable = detach the pivot only. The provisioned account-level row is
        // left in place (it may back other agents / the analytics chart server).
        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $slug)
            ->first();

        if ($server !== null) {
            $agent->detachConnector($server);
        }

        return response()->json(['ok' => true]);
    }
}
