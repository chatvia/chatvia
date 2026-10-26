<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Account\ConnectTokenConnectorAction;
use Domain\Integration\Connector\Data\Account\ConnectTokenConnectorData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Exceptions\CapabilityRequiresConnectorException;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Connector\Services\ConnectorPrerequisite;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Enable a first-party capability (e.g. Interactive Charts) for one agent.
 *
 * Capabilities are no-auth builtins, so "enabling" is a single toggle: the
 * first enable anywhere in the workspace connects the workspace-level server
 * (the same {@see ConnectTokenConnectorAction} the Connectors page uses, so
 * the server and its tool catalog are identical), then the server is attached
 * to this agent. Later enables only attach. A capability that works through
 * another connector (Calendar through Calendly) is refused until the
 * workspace has connected it.
 */
class EnableAgentCapabilityAction
{
    public function __construct(
        private readonly ConnectTokenConnectorAction $connect,
        private readonly ConnectorPrerequisite $prerequisite,
        private readonly ConnectorCatalog $catalog,
    ) {}

    /**
     * @throws CapabilityRequiresConnectorException
     * @throws McpException when the first connect cannot sync the capability's tools
     */
    public function execute(Agent $agent, ConnectorData $capability): AgentMcpServer
    {
        if (! $this->prerequisite->isMet($agent, $capability)) {
            throw new CapabilityRequiresConnectorException(
                $this->catalog->find((string) $capability->requiresConnector)?->name ?? (string) $capability->requiresConnector,
            );
        }

        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $capability->slug)
            ->first();

        // Connected in the agent's workspace, on behalf of its owner.
        $server ??= $this->connect->execute(new ConnectTokenConnectorData(
            user: $agent->workspace->owner ?? throw new \LogicException('A workspace always has an owner.'),
            connector: $capability,
        ))->server;

        $agent->attachConnector($server);

        return $server;
    }
}
