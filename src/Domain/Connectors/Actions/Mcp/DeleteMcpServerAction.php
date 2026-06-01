<?php

namespace Domain\Connectors\Actions\Mcp;

use Domain\Agents\Models\AgentMcpServer;

class DeleteMcpServerAction
{
    public function execute(AgentMcpServer $server): void
    {
        $server->delete();
    }
}
