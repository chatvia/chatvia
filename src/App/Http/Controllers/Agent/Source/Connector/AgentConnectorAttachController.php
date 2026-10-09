<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Agent\AgentConnectorAttachResultData;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Attach one of the workspace's connectors to this agent. Access is gated by
 * the route middleware (`can.access.agent`, `agent.permission:…`).
 */
class AgentConnectorAttachController
{
    public function __invoke(Agent $agent, string $slug): AgentConnectorAttachResultData
    {
        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404, __('Connector not found in your account.'));

        $agent->attachConnector($server);

        return new AgentConnectorAttachResultData(
            data: AgentMcpServerData::fromModel($server->fresh()),
        );
    }
}
