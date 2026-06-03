<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\RefreshAgentConnectorCatalogAction;
use Domain\Integration\Connector\Data\Agent\AgentConnectorRefreshResultData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
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
