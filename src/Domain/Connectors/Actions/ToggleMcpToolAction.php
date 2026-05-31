<?php

namespace Domain\Connectors\Actions;

use Domain\Agents\Models\AgentMcpServer;
use Domain\Agents\Models\McpTool;
use Domain\Connectors\Data\ToggledConnectorToolData;
use RuntimeException;

/**
 * Flip the `is_enabled` flag on a single tool inside a connector. Throws when the
 * tool doesn't belong to the given server so the caller can surface a 404.
 */
class ToggleMcpToolAction
{
    public function execute(AgentMcpServer $server, int $toolId): ToggledConnectorToolData
    {
        /** @var McpTool|null $tool */
        $tool = $server->tools()->whereKey($toolId)->first();

        if ($tool === null) {
            throw new RuntimeException('Tool not found.');
        }

        $tool->forceFill(['is_enabled' => ! $tool->is_enabled])->save();

        return new ToggledConnectorToolData(
            id: $tool->id,
            is_enabled: (bool) $tool->is_enabled,
        );
    }
}
