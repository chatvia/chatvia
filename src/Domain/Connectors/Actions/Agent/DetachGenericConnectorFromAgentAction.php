<?php

namespace Domain\Connectors\Actions\Agent;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;

class DetachGenericConnectorFromAgentAction
{
    public function execute(Agent $agent, AgentMcpServer $server): void
    {
        $agent->mcpServers()->detach($server->id);
    }
}
