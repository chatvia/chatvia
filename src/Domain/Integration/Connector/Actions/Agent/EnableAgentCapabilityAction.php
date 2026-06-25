<?php

namespace Domain\Integration\Connector\Actions\Agent;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
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
 *
 * A capability may declare {@see ConnectorData::$companions} — hidden builtin
 * servers attached alongside it (the Forms capability companions the `leads`
 * server so conversational capture-lead works off-web). Companions are resolved
 * by slug via the catalog, so they may be visible:false.
 */
class EnableAgentCapabilityAction
{
    public function __construct(
        private readonly TokenConnectorAuthorizer $authorizer,
        private readonly ConnectorCatalog $catalog,
    ) {}

    public function execute(Agent $agent, User $user, ConnectorData $capability): AgentMcpServer
    {
        $server = $this->provisionAndAttach($agent, $user, $capability);

        foreach ($capability->companions as $companionSlug) {
            $companion = $this->catalog->find($companionSlug);
            if ($companion !== null) {
                $this->provisionAndAttach($agent, $user, $companion);
            }
        }

        return $server;
    }

    private function provisionAndAttach(Agent $agent, User $user, ConnectorData $connector): AgentMcpServer
    {
        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $connector->slug)
            ->first();

        // First enable anywhere in the workspace provisions the account-level row
        // (a remote tools/list refresh). Subsequent enables just attach the pivot.
        if ($server === null) {
            $server = $this->authorizer->connect($user, $connector)['server'];
        }

        $agent->attachConnector($server);

        return $server;
    }
}
