<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Agents\Models\McpTool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns the MCP tool catalog (from the `mcp_tools` table) for a
 * connector this agent has attached, along with each tool's per-server
 * enabled flag and embedding-readiness flag.
 */
class AgentConnectorToolsController
{
    public function __invoke(Request $request, Agent $agent, string $slug): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $server = AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('slug', $slug)
            ->whereExists(function ($q) use ($agent): void {
                $q->from('agent_mcp_server')
                    ->whereColumn('agent_mcp_server.mcp_server_id', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agent->id);
            })
            ->first();

        abort_unless($server !== null, 404);

        $tools = $server->tools()->orderBy('name')->get();

        $flattened = $tools->map(fn (McpTool $tool): array => [
            'name' => $server->toolPrefix().$tool->name,
            'short_name' => $tool->name,
            'description' => $tool->description,
            'parameters' => $tool->input_schema,
            'enabled' => $tool->enabled,
            'embedded_at' => $tool->embedded_at?->toISOString(),
            'id' => $tool->id,
        ])->all();

        return response()->json([
            'tools' => $flattened,
            'cached_at' => $server->catalog_cached_at,
        ]);
    }
}
