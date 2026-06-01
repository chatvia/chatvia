<?php

namespace Domain\Connectors\Actions\Mcp;

use Domain\Agents\Models\AgentMcpServer;

class ToggleMcpServerAction
{
    public function execute(AgentMcpServer $server): AgentMcpServer
    {
        $server->forceFill(['is_enabled' => ! $server->is_enabled])->save();

        return $server->fresh();
    }
}
