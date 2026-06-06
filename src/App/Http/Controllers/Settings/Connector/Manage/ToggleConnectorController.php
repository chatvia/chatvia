<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Controller;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flip an account-level MCP/connector's `enabled` flag.
 */
class ToggleConnectorController extends Controller
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        $server = AgentMcpServer::forSlugScoped($slug, $request->user());
        $server->forceFill(['is_enabled' => ! $server->is_enabled])->save();

        return response()->json(['data' => AgentMcpServerData::fromModel($server->fresh())]);
    }
}
