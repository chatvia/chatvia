<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConnectorDisconnectController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        string $slug,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);

        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404, 'Connector not found in your account.');

        $agent->detachConnector($server);

        return response()->json(['ok' => true]);
    }
}
