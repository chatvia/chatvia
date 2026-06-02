<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Connector\Actions\Agent\RefreshAgentConnectorCatalogAction;
use Domain\Connector\Data\Agent\AgentConnectorRefreshResultData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgentConnectorRefreshController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        string $slug,
        RefreshAgentConnectorCatalogAction $refresh,
    ): JsonResponse|AgentConnectorRefreshResultData {
        abort_if($request->user()->cannot('update', $agent), 404);

        $server = AgentMcpServer::query()
            ->attachedToAgent($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404);

        try {
            return $refresh->execute($server);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
