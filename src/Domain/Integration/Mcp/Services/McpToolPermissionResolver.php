<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a tool's stored permission (+ description) for a given server,
 * branching on the server's storage shape:
 *
 *   - preset/builtin → the per-workspace `mcp_server_tool` membership joined to
 *     the shared `connector_tools` catalog.
 *   - custom         → the per-server `mcp_tools` row.
 *
 * Shared by McpToolDispatcher (blocked-check + invocation description) and the
 * ToolApprovalPolicy so the two resolve identically — the policy gates exactly
 * the calls the dispatcher runs.
 *
 * Returns null when the catalog doesn't know the tool (a stale catalog). Callers
 * decide what null means: the approval policy gates it; the dispatcher lets it
 * through (the catalog, not the dispatcher, hides blocked tools from the model).
 */
class McpToolPermissionResolver
{
    public function resolve(AgentMcpServer $server, string $toolName): ?ResolvedMcpTool
    {
        $row = $server->usesCentralCatalog()
            ? DB::table('mcp_server_tool as mst')
                ->join('connector_tools as ct', 'ct.id', '=', 'mst.connector_tool_id')
                ->where('mst.mcp_server_id', $server->id)
                ->where('ct.connector_slug', $server->connector_slug)
                ->where('ct.name', $toolName)
                ->select(['mst.permission', 'ct.description'])
                ->first()
            : DB::table('mcp_tools')
                ->where('mcp_server_id', $server->id)
                ->where('name', $toolName)
                ->select(['permission', 'description'])
                ->first();

        if ($row === null) {
            return null;
        }

        return new ResolvedMcpTool(
            permission: McpToolPermission::from((string) $row->permission),
            description: $row->description !== null ? (string) $row->description : null,
        );
    }
}
