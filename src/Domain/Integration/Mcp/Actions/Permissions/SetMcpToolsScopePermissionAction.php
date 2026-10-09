<?php

namespace Domain\Integration\Mcp\Actions\Permissions;

use Domain\Integration\Mcp\Data\McpToolsScopePermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * Bulk-set the permission (allow / approval / blocked) on every tool in one
 * scope (read or write) of a connector — the "apply to the whole section"
 * action behind the section dropdown in the Connectors UI.
 *
 * Mirrors {@see SetMcpToolPermissionAction}'s storage branching. Drops the
 * cached tool pool of every attached agent when anything changed, since the
 * pool leaves out blocked tools.
 */
class SetMcpToolsScopePermissionAction
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function execute(AgentMcpServer $server, McpToolScope $scope, McpToolPermission $permission): McpToolsScopePermissionData
    {
        $updated = $server->usesCentralCatalog()
            ? $this->setMembershipScope($server, $scope, $permission)
            : $this->setOwnToolsScope($server, $scope, $permission);

        if ($updated > 0) {
            $this->registry->forgetAttachedTo($server);
        }

        return new McpToolsScopePermissionData(
            scope: $scope,
            permission: $permission,
            updated: $updated,
        );
    }

    /**
     * Every membership whose catalog tool falls in this scope. Tools the
     * server doesn't advertise have no membership and are left alone.
     */
    private function setMembershipScope(AgentMcpServer $server, McpToolScope $scope, McpToolPermission $permission): int
    {
        $connectorToolIds = $server->connectorTools()
            ->where('connector_tools.scope', $scope->value)
            ->pluck('connector_tools.id');

        if ($connectorToolIds->isEmpty()) {
            return 0;
        }

        return $server->connectorTools()->newPivotQuery()
            ->whereIn('connector_tool_id', $connectorToolIds)
            ->update(['permission' => $permission->value]);
    }

    private function setOwnToolsScope(AgentMcpServer $server, McpToolScope $scope, McpToolPermission $permission): int
    {
        return $server->tools()
            ->where('scope', $scope->value)
            ->update(['permission' => $permission->value]);
    }
}
