<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use App\Http\Requests\Agents\Connectors\AgentConnectorConnectRequest;
use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Services\ConnectorCatalog;
use Domain\Connectors\Services\TokenConnectorAuthorizer;
use Illuminate\Http\JsonResponse;

class AgentConnectorConnectController
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly TokenConnectorAuthorizer $authorizer,
    ) {}

    public function __invoke(AgentConnectorConnectRequest $request, Agent $agent, string $slug): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $connector = $this->catalog->findTokenConnector($slug);
        abort_unless($connector !== null, 404, 'Connector not available');

        // Token connectors must include a token; no-auth connectors must not.
        if ($connector->authStrategy === 'token' && ! $request->filled('token')) {
            return response()->json(['message' => 'A token is required for this connector.'], 422);
        }

        try {
            $result = $this->authorizer->connect(
                $agent,
                $connector,
                $connector->authStrategy === 'token' ? $request->validated('token') : null,
            );
        } catch (McpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($result['server']),
            'tools_count' => $result['tools_count'],
        ], 201);
    }
}
