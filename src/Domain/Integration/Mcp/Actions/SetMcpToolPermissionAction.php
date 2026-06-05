<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Integration\Mcp\Data\McpToolPermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use RuntimeException;

/**
 * Set a single tool's `permission` (allow / approval / blocked) inside a
 * connector. Throws when the tool doesn't belong to the given server so the
 * caller can surface a 404.
 */
class SetMcpToolPermissionAction
{
    public function execute(AgentMcpServer $server, int $toolId, McpToolPermission $permission): McpToolPermissionData
    {
        /** @var McpTool|null $tool */
        $tool = $server->tools()->whereKey($toolId)->first();

        if ($tool === null) {
            throw new RuntimeException('Tool not found.');
        }

        $tool->forceFill(['permission' => $permission])->save();

        return new McpToolPermissionData(
            id: $tool->id,
            permission: $tool->permission,
        );
    }
}
