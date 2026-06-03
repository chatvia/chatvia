<?php

namespace App\Http\Controllers\Settings\Connector\Provisioning;

use App\Http\Controllers\Controller;
use App\Identity\Workspace\Models\User;
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

        $server = $this->find($request, $slug);

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

    private function find(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();
        $server = $user->mcpServers()->where('slug', $slug)->first();

        abort_unless($server !== null, 404);

        return $server;
    }
}
