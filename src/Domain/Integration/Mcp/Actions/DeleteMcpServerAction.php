<?php

namespace Domain\Integration\Mcp\Actions;

use Domain\Integration\Mcp\Models\AgentMcpServer;

class DeleteMcpServerAction
{
    public function execute(AgentMcpServer $server): void
    {
        $server->delete();
    }
}
