<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Integration\Mcp\Data\McpToolPermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Set a single tool's `permission` (allow / approval / blocked) inside a
 * connector. Branches on the server's storage shape: a preset/builtin server
 * toggles the per-workspace `mcp_server_tool` membership row (the tool id is a
 * connector_tools.id); a custom server toggles its own `mcp_tools` row.
 *
 * Throws when the tool doesn't belong to the given server so the caller can
 * surface a 404. Busts the compiled tool-pool cache for every agent the server
 * is attached to, since the pool excludes blocked tools.
 */
class SetMcpToolPermissionAction
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    public function execute(AgentMcpServer $server, int $toolId, McpToolPermission $permission): McpToolPermissionData
    {
        $server->usesCentralCatalog()
            ? $this->setPresetPermission($server, $toolId, $permission)
            : $this->setCustomPermission($server, $toolId, $permission);

        $this->forgetAttachedAgentPools($server);

        return new McpToolPermissionData(
            id: $toolId,
            permission: $permission,
        );
    }

    /**
     * Preset/builtin: `$toolId` is a connector_tools.id; flip the per-workspace
     * membership. A connector_tool the server doesn't advertise has no
     * membership → 404.
     */
    private function setPresetPermission(AgentMcpServer $server, int $connectorToolId, McpToolPermission $permission): void
    {
        $updated = DB::table('mcp_server_tool')
            ->where('mcp_server_id', $server->id)
            ->where('connector_tool_id', $connectorToolId)
            ->update(['permission' => $permission->value]);

        if ($updated === 0) {
            throw new RuntimeException('Tool not found.');
        }
    }

    private function setCustomPermission(AgentMcpServer $server, int $toolId, McpToolPermission $permission): void
    {
        /** @var McpTool|null $tool */
        $tool = $server->tools()->whereKey($toolId)->first();

        if ($tool === null) {
            throw new RuntimeException('Tool not found.');
        }

        $tool->forceFill(['permission' => $permission])->save();
    }

    private function forgetAttachedAgentPools(AgentMcpServer $server): void
    {
        DB::table('agent_mcp_server')
            ->where('mcp_server_id', $server->id)
            ->pluck('agent_id')
            ->each(fn ($agentId) => $this->registry->forget((int) $agentId));
    }
}
