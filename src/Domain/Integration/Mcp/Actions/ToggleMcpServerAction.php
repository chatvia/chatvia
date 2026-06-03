<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Integration\Mcp\Models\AgentMcpServer;

class ToggleMcpServerAction
{
    public function execute(AgentMcpServer $server): AgentMcpServer
    {
        $server->forceFill(['is_enabled' => ! $server->is_enabled])->save();

        return $server->fresh();
    }
}
