<?php

namespace App\Http\Controllers\Settings\Connector\Catalog;

use App\Http\Requests\Settings\Connector\ConnectTokenConnectorRequest;
use Domain\Integration\Connector\Actions\Account\ConnectTokenConnectorAction;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Exceptions\McpException;
use Illuminate\Http\JsonResponse;

/**
 * Connects a token / no-auth catalog connector for the workspace. OAuth
 * connectors go through `OAuthStartController` instead.
 */
class ConnectController
{
    public function __invoke(ConnectTokenConnectorRequest $request, ConnectTokenConnectorAction $connect): JsonResponse
    {
        try {
            $result = $connect->execute($request->toData());
        } catch (McpException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($result->server),
            'tools_count' => $result->tools_count,
        ], 201);
    }
}
