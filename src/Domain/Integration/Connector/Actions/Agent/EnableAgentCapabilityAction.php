<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Account\ConnectTokenConnectorAction;
use Domain\Integration\Connector\Data\Account\ConnectTokenConnectorData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Enable a first-party capability (e.g. Interactive Charts) for one agent.
 *
 * Capabilities are no-auth builtins, so "enabling" is a single toggle: the
 * first enable anywhere in the workspace connects the workspace-level server
 * (the same {@see ConnectTokenConnectorAction} the Connectors page uses, so
 * the server and its tool catalog are identical), then the server is attached
 * to this agent. Later enables only attach.
 */
class EnableAgentCapabilityAction
{
    public function __construct(
        private readonly ConnectTokenConnectorAction $connect,
    ) {}

    public function execute(Agent $agent, ConnectorData $capability): AgentMcpServer
    {
        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $capability->slug)
            ->first();

        // Connected under the agent's owner — the workspace the server belongs to.
        $server ??= $this->connect->execute(new ConnectTokenConnectorData(
            user: $agent->user,
            connector: $capability,
        ))->server;

        $agent->attachConnector($server);

        return $server;
    }
}
