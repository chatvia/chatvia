<?php

namespace App\Http\Controllers\Settings\Connector\Catalog;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\TokenConnectorAuthorizer;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Services\McpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provisions a token / no-auth catalog connector at the account level.
 * OAuth connectors go through `ConnectorOAuthStartController` instead.
 */
class ConnectController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly TokenConnectorAuthorizer $authorizer,
    ) {}

    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $connector = $this->catalog->findTokenConnector($slug);
        abort_unless($connector !== null, 404, 'Connector not available');

        if ($connector->authStrategy === 'token' && ! $request->filled('token')) {
            return response()->json(['message' => 'A token is required for this connector.'], 422);
        }

        // Presets without a first-party MCP server (e.g. Zendesk) carry no fixed
        // `mcpUrl`; the user supplies their own server URL alongside the token.
        $rules = ['token' => ['sometimes', 'nullable', 'string', 'max:8192']];
        if ($connector->requiresUrl) {
            $rules['url'] = ['required', 'url', 'max:2048', new SafeWebhookUrl];
        }
        $request->validate($rules);

        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->authorizer->connect(
                $user,
                $connector,
                $connector->authStrategy === 'token' ? (string) $request->input('token') : null,
                $connector->requiresUrl ? (string) $request->input('url') : null,
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
