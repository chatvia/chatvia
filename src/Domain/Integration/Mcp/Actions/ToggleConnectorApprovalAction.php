<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Flip a connector's master `requires_approval` switch. When on, the
 * connector's write/destructive tools (per-tool `requires_approval`) pause for
 * the user's confirmation; when off, everything from this connector auto-runs.
 */
class ToggleConnectorApprovalAction
{
    public function execute(AgentMcpServer $server): AgentMcpServer
    {
        $server->forceFill(['requires_approval' => ! $server->requires_approval])->save();

        return $server->fresh();
    }
}
