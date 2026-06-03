<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Integration\Mcp\Data\ToggledConnectorToolData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use RuntimeException;

/**
 * Flip the per-tool `requires_approval` override on a single tool inside a
 * connector. Only effective when the parent connector's master switch is on.
 */
class ToggleMcpToolApprovalAction
{
    public function execute(AgentMcpServer $server, int $toolId): ToggledConnectorToolData
    {
        /** @var McpTool|null $tool */
        $tool = $server->tools()->whereKey($toolId)->first();

        if ($tool === null) {
            throw new RuntimeException('Tool not found.');
        }

        $tool->forceFill(['requires_approval' => ! $tool->requires_approval])->save();

        return new ToggledConnectorToolData(
            id: $tool->id,
            is_enabled: (bool) $tool->is_enabled,
            requires_approval: (bool) $tool->requires_approval,
        );
    }
}
