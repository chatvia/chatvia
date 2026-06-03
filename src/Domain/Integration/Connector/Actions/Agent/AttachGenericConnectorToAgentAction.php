<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;

class AttachGenericConnectorToAgentAction
{
    public function execute(Agent $agent, AgentMcpServer $server): AgentMcpServer
    {
        $agent->mcpServers()->syncWithoutDetaching([$server->id]);

        return $server->fresh();
    }
}
