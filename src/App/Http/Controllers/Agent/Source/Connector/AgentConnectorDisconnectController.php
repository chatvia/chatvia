<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;

/**
 * Detach a connector from this agent. The workspace's connector itself stays
 * (other agents may use it). Access is gated by the route middleware.
 */
class AgentConnectorDisconnectController
{
    public function __invoke(Agent $agent, string $slug): JsonResponse
    {
        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404, __('Connector not found in your account.'));

        $agent->detachConnector($server);

        return response()->json(['ok' => true]);
    }
}
