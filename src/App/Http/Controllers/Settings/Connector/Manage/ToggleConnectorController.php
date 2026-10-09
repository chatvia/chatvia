<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flip a workspace connector's on/off switch.
 */
class ToggleConnectorController
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $server = AgentMcpServer::forSlugScoped($slug, $request->user());
        $server->toggleEnabled();

        return response()->json(['data' => AgentMcpServerData::fromModel($server->fresh())]);
    }
}
