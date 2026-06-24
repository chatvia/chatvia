<?php

namespace Domain\Integration\Connector\Actions\Agent;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\TokenConnectorAuthorizer;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Enable a first-party capability (e.g. Interactive Charts) for one agent.
 *
 * Capabilities are no-auth builtins, so "enabling" is a single toggle: provision
 * the account-level MCP server row the first time anyone enables it, then attach
 * it to this agent via the `agent_mcp_server` pivot. Provisioning reuses the same
 * {@see TokenConnectorAuthorizer} path the workspace Connectors page uses, so the
 * server (and its tool catalog) is identical to a manually-connected builtin.
 */
class EnableAgentCapabilityAction
{
    public function __construct(
        private readonly TokenConnectorAuthorizer $authorizer,
    ) {}

    public function execute(Agent $agent, User $user, ConnectorData $capability): AgentMcpServer
    {
        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $capability->slug)
            ->first();

        // First enable anywhere in the workspace provisions the account-level row
        // (a remote tools/list refresh). Subsequent enables just attach the pivot.
        if ($server === null) {
            $server = $this->authorizer->connect($user, $capability)['server'];
        }

        $agent->attachConnector($server);

        return $server;
    }
}
