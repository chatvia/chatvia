<?php

namespace Domain\Connectors\Actions;

use Domain\Agents\Models\AgentMcpServer;

class ToggleMcpServerAction
{
    public function execute(AgentMcpServer $server): AgentMcpServer
    {
        $server->forceFill(['enabled' => ! $server->enabled])->save();

        return $server->fresh();
    }
}
