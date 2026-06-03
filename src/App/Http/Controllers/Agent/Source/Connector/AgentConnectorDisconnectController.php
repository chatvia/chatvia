<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\DetachGenericConnectorFromAgentAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConnectorDisconnectController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        string $slug,
        DetachGenericConnectorFromAgentAction $detach,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 403);

        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404, 'Connector not found in your account.');

        $detach->execute($agent, $server);

        return response()->json(['ok' => true]);
    }
}
