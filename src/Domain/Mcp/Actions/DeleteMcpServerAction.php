<?php

namespace Domain\Mcp\Actions;

use Domain\Mcp\Models\AgentMcpServer;

class DeleteMcpServerAction
{
    public function execute(AgentMcpServer $server): void
    {
        $server->delete();
    }
}
