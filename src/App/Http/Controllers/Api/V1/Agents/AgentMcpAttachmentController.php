<?php

namespace App\Http\Controllers\Api\V1\Agents;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Agent MCP Attachments
 *
 * Attach / detach account-level MCP servers to a specific agent. The MCP
 * definitions themselves are managed via `/api/v1/mcps` — this endpoint
 * only manages which agents can use which definitions. Presence of the
 * pivot row means "attached"; to pause, detach.
 */
class AgentMcpAttachmentController
{
    /**
     * List attached MCPs
     */
    public function index(Request $request, Agent $agent): JsonResponse
    {
        $servers = $agent->mcpServers()
            ->whereNull('connector_slug')
            ->get();

        return response()->json([
            'data' => $servers->map(fn (AgentMcpServer $m): array => $this->transform($m))->all(),
        ]);
    }

    /**
     * Attach MCP
     *
     * Body: `{ mcp_id: string }`. The `mcp_id` is the `id` returned by
     * `POST /api/v1/mcps`. Idempotent — re-attaching is a no-op.
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'mcp_id' => ['required', 'string'],
        ]);

        $server = $this->resolveServer($agent, $validated['mcp_id']);

        $agent->mcpServers()->syncWithoutDetaching([$server->id]);

        return response()->json(['mcp' => $this->transform($server->fresh())], 201);
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
            ->whereNull('connector_slug')
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $server;
    }

    private function transform(AgentMcpServer $server): array
    {
        return [
            'id' => $server->external_id,
            'name' => $server->name,
            'slug' => $server->slug,
            'url' => $server->url,
            'transport' => $server->transport,
            'auth_type' => $server->auth_type,
            'enabled' => $server->enabled,
            'created_at' => $server->created_at?->toIso8601String(),
        ];
    }
}
