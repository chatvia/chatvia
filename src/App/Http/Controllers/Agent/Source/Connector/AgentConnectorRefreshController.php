<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Catalog\RefreshConnectorCatalogAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorRefreshResultData;
use Domain\Integration\Connector\Exceptions\ConnectorRefreshFailedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;

/**
 * Re-fetch the tool catalog of a connector attached to this agent; a failed
 * refresh comes back as a 422 with a message the page can show.
 */
class AgentConnectorRefreshController
{
    public function __invoke(Agent $agent, string $slug, RefreshConnectorCatalogAction $refresh): JsonResponse|ConnectorRefreshResultData
    {
        $server = AgentMcpServer::query()
            ->attachedToAgent($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404);

        try {
            return $refresh->execute($server);
        } catch (ConnectorRefreshFailedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
