<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AgentConnectorRefreshController
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Agent $agent, string $slug): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $server = $agent->mcpServers()
            ->where('connector_slug', $slug)
            ->first();

        abort_unless($server, 404);

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
