<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Integration\Mcp\Data\McpToolsScopePermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-set the permission (allow / approval / blocked) on every tool in one
 * scope (read or write) of a connector — the "apply to the whole section"
 * action behind the section dropdown in the Connectors UI.
 *
 * Mirrors {@see SetMcpToolPermissionAction}'s storage branching: a
 * preset/builtin server flips the per-workspace `mcp_server_tool` memberships
 * for that scope's connector_tools; a custom server updates its own `mcp_tools`
 * rows. Busts the compiled tool-pool cache for every attached agent when
 * anything changed, since the pool excludes blocked tools.
 */
class SetMcpToolsScopePermissionAction
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function execute(AgentMcpServer $server, McpToolScope $scope, McpToolPermission $permission): McpToolsScopePermissionData
    {
        $updated = $server->usesCentralCatalog()
            ? $this->setPresetScope($server, $scope, $permission)
            : $this->setCustomScope($server, $scope, $permission);

        if ($updated > 0) {
            $this->forgetAttachedAgentPools($server);
        }

        return new McpToolsScopePermissionData(
            scope: $scope,
            permission: $permission,
            updated: $updated,
        );
    }

    /**
     * Preset/builtin: flip every `mcp_server_tool` membership whose
     * connector_tool falls in this scope. Tools the server doesn't advertise
     * have no membership and are left alone.
     */
    private function setPresetScope(AgentMcpServer $server, McpToolScope $scope, McpToolPermission $permission): int
    {
        $connectorToolIds = $server->connectorTools()
            ->where('connector_tools.scope', $scope->value)
            ->pluck('connector_tools.id');

        if ($connectorToolIds->isEmpty()) {
            return 0;
        }

        return DB::table('mcp_server_tool')
            ->where('mcp_server_id', $server->id)
            ->whereIn('connector_tool_id', $connectorToolIds)
            ->update(['permission' => $permission->value]);
    }

    private function setCustomScope(AgentMcpServer $server, McpToolScope $scope, McpToolPermission $permission): int
    {
        return $server->tools()
            ->where('scope', $scope->value)
            ->update(['permission' => $permission->value]);
    }

    private function forgetAttachedAgentPools(AgentMcpServer $server): void
    {
        DB::table('agent_mcp_server')
            ->where('mcp_server_id', $server->id)
            ->pluck('agent_id')
            ->each(fn ($agentId) => $this->registry->forget((int) $agentId));
    }
}
