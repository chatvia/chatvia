<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Actions\Permissions;

use Domain\Integration\Mcp\Data\McpToolPermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Exceptions\McpToolNotFoundException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Registry\ToolRegistry;

/**
 * Set a single tool's `permission` (allow / approval / blocked) inside a
 * connector. Branches on the server's storage shape: a shared-catalog server
 * changes its `connector_tool_mcp_server` membership row (the tool id is a
 * connector_tools.id); a server with its own catalog changes its `mcp_tools`
 * row.
 *
 * Throws {@see McpToolNotFoundException} when the tool doesn't belong to the
 * given server, so the caller can answer 404. Drops the cached tool pool of
 * every agent the server is attached to, since the pool leaves out blocked
 * tools.
 */
class SetMcpToolPermissionAction
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    /**
     * @param  McpToolPermissionData  $change  the tool id (on this server) and its new permission
     * @return McpToolPermissionData the stored change, for the page to patch its row
     *
     * @throws McpToolNotFoundException
     */
    public function execute(AgentMcpServer $server, McpToolPermissionData $change): McpToolPermissionData
    {
        $server->usesCentralCatalog()
            ? $this->setMembershipPermission($server, $change->id, $change->permission)
            : $this->setOwnToolPermission($server, $change->id, $change->permission);

        $this->registry->forgetAttachedTo($server);

        return $change;
    }

    /**
     * A catalog tool the server doesn't advertise has no membership → 404.
     */
    private function setMembershipPermission(AgentMcpServer $server, int $connectorToolId, McpToolPermission $permission): void
    {
        $updated = $server->connectorTools()->newPivotQuery()
            ->where('connector_tool_id', $connectorToolId)
            ->update(['permission' => $permission->value]);

        if ($updated === 0) {
            throw McpToolNotFoundException::onServer($connectorToolId, (string) $server->slug);
        }
    }

    private function setOwnToolPermission(AgentMcpServer $server, int $toolId, McpToolPermission $permission): void
    {
        /** @var McpTool|null $tool */
        $tool = $server->tools()->whereKey($toolId)->first();

        if ($tool === null) {
            throw McpToolNotFoundException::onServer($toolId, (string) $server->slug);
        }

        $tool->forceFill(['permission' => $permission])->save();
    }
}
