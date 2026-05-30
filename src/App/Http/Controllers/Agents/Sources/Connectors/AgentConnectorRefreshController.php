<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\RefreshAgentConnectorCatalogAction;
use Domain\Connectors\Data\AgentConnectorRefreshResultData;
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
