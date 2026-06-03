<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Http\Resources\Api\V1\Tools\McpServerData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Integration\Mcp\Actions\DeleteMcpServerAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Your custom MCP servers. Create and edit them in the dashboard (Connectors);
 * here you can list, inspect, and delete them, and attach them to agents via
 * `POST /api/v1/agents/{id}/mcps`. Built-in catalog connectors are never
 * exposed here — only your custom entries. Auth secrets are never returned.
 */
#[Group(name: 'MCP Servers', weight: 50)]
class McpServerController
{
    /**
     * List MCP servers
     *
     * @return array{data: list<McpServerData>}
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $servers = $user->mcpServers()
            ->whereNull('connector_slug')
            ->latest()
            ->get();

        return ['data' => $servers->map(fn (AgentMcpServer $server): McpServerData => McpServerData::fromModel($server))->all()];
    }

    /**
     * Get MCP server
     *
     * @return array{data: McpServerData}
     */
    public function show(Request $request, string $mcp): array
    {
        return ['data' => McpServerData::fromModel($this->findCustom($request, $mcp))];
    }

    /**
     * Delete MCP server
     *
     * Also removes it from every agent it's attached to.
     */
    public function destroy(Request $request, string $mcp, DeleteMcpServerAction $deleteServer): JsonResponse
    {
        $deleteServer->execute($this->findCustom($request, $mcp));

        return response()->json(null, 204);
    }

    private function findCustom(Request $request, string $externalId): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();

        /** @var AgentMcpServer $server */
        $server = $user->mcpServers()
            ->whereNull('connector_slug')
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $server;
    }
}
