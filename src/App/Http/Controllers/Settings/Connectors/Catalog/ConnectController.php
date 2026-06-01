<?php

namespace App\Http\Controllers\Settings\Connectors\Catalog;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Data\Resources\Mcp\AgentMcpServerData;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Services\ConnectorCatalog;
use Domain\Connectors\Services\TokenConnectorAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provisions a token / no-auth catalog connector at the account level.
 * OAuth connectors go through `ConnectorOAuthStartController` instead.
 */
class ConnectController extends Controller
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly TokenConnectorAuthorizer $authorizer,
    ) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        $connector = $this->catalog->findTokenConnector($slug);
        abort_unless($connector !== null, 404, 'Connector not available');

        if ($connector->authStrategy === 'token' && ! $request->filled('token')) {
            return response()->json(['message' => 'A token is required for this connector.'], 422);
        }

        $request->validate(['token' => ['sometimes', 'nullable', 'string', 'max:8192']]);

        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->authorizer->connect(
                $user,
                $connector,
                $connector->authStrategy === 'token' ? (string) $request->input('token') : null,
            );
        } catch (McpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($result['server']),
            'tools_count' => $result['tools_count'],
        ], 201);
    }
}
