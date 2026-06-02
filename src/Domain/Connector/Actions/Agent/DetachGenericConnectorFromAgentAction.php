<?php

namespace Domain\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Mcp\Models\AgentMcpServer;

class DetachGenericConnectorFromAgentAction
{
    public function execute(Agent $agent, AgentMcpServer $server): void
    {
        $agent->mcpServers()->detach($server->id);
    }
}
