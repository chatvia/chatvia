<?php

namespace Domain\Connectors\Actions;

use Domain\Agents\Models\AgentMcpServer;

class DeleteMcpServerAction
{
    public function execute(AgentMcpServer $server): void
    {
        $server->delete();
    }
}
