<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use Domain\Integration\Connector\Actions\Catalog\RefreshConnectorCatalogAction;
use Domain\Integration\Connector\Exceptions\ConnectorRefreshFailedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Re-fetch the tool catalog of a workspace connector; a failed refresh comes
 * back as a 422 with a message the page can show.
 */
class RefreshConnectorController
{
    public function __invoke(Request $request, string $slug, RefreshConnectorCatalogAction $refresh): JsonResponse
    {
        try {
            return response()->json($refresh->execute(AgentMcpServer::forSlugScoped($slug, $request->user())));
        } catch (ConnectorRefreshFailedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
