<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Integration\Mcp\Data\ResolvedMcpTool;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;

/**
 * Resolves a tool's stored permission (+ description and input schema) for a given server,
 * branching on the server's storage shape:
 *
 *   - preset/builtin → the per-workspace `connector_tool_mcp_server` membership joined to
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
        return $server->usesCentralCatalog()
            ? $this->fromMembership($server, $toolName)
            : $this->fromOwnTools($server, $toolName);
    }

    private function fromMembership(AgentMcpServer $server, string $toolName): ?ResolvedMcpTool
    {
        /** @var ConnectorTool|null $tool */
        $tool = $server->connectorTools()
            ->where('connector_tools.connector_slug', $server->connector_slug)
            ->where('connector_tools.name', $toolName)
            ->first(['connector_tools.id', 'connector_tools.description', 'connector_tools.input_schema']);

        if ($tool === null) {
            return null;
        }

        /** @var McpServerTool $membership */
        $membership = $tool->getRelation('pivot');

        return new ResolvedMcpTool($membership->permission, $tool->description, $tool->input_schema);
    }

    private function fromOwnTools(AgentMcpServer $server, string $toolName): ?ResolvedMcpTool
    {
        /** @var McpTool|null $tool */
        $tool = $server->tools()->where('name', $toolName)->first(['permission', 'description', 'input_schema']);

        return $tool !== null ? new ResolvedMcpTool($tool->permission, $tool->description, $tool->input_schema) : null;
    }
}
