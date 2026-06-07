<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Http\Controllers\Controller;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Domain\Integration\Mcp\Services\McpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Re-fetch the tool catalog for an account-level MCP/connector.
 */
class RefreshConnectorController extends Controller
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        $server = AgentMcpServer::forSlugScoped($slug, $request->user());

        try {
            $tools = $this->catalog->refresh($server);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e instanceof McpException ? $e->getMessage() : 'Could not refresh the connector.',
            ], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($server->fresh()),
            'tools_count' => count($tools),
        ]);
    }
}
