<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Resources\Api\V1\McpServerData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attach / detach MCP servers to a specific agent. Any server connected to
 * your account — whether added by URL or picked from the catalog — can be
 * attached by its `id`. An attached server stays available to the agent until
 * you detach it.
 */
#[Group(name: 'MCP Servers', weight: 50)]
class AgentMcpAttachmentController
{
    /**
     * List attached MCPs
     *
     * @return array{data: list<McpServerData>}
     */
    public function index(Request $request, Agent $agent): array
    {
        $servers = $agent->mcpServers()->get();

        return ['data' => $servers->map(fn (AgentMcpServer $server): McpServerData => McpServerData::fromModel($server))->all()];
    }

    /**
     * Attach MCP
     *
     * Body: `{ mcp_id: string }` — the server's `id`, copied from the
     * dashboard (Connectors) or returned by `GET /api/v1/mcps`. Idempotent —
     * re-attaching is a no-op.
     *
     * @return array{data: McpServerData}
     */
    public function store(Request $request, Agent $agent): array
    {
        $validated = $request->validate([
            'mcp_id' => ['required', 'string'],
        ]);

        $server = $this->resolveServer($agent, $validated['mcp_id']);

        $agent->mcpServers()->syncWithoutDetaching([$server->id]);

        return ['data' => McpServerData::fromModel($server->fresh())];
    }

    /**
     * Detach MCP
     *
     * The definition itself is not deleted — only the agent's attachment.
     */
    public function destroy(Request $request, Agent $agent, string $mcp): JsonResponse
    {
        $server = $this->resolveServer($agent, $mcp);

        $agent->mcpServers()->detach($server->id);

        return response()->json(null, 204);
    }

    private function resolveServer(Agent $agent, string $externalId): AgentMcpServer
    {
        /** @var AgentMcpServer $server */
        $server = AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $server;
    }
}
