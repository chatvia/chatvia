<?php

namespace Domain\Connectors\Actions\Agent;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;

class AttachGenericConnectorToAgentAction
{
    public function execute(Agent $agent, AgentMcpServer $server): AgentMcpServer
    {
        $agent->mcpServers()->syncWithoutDetaching([$server->id]);

        return $server->fresh();
    }
}
