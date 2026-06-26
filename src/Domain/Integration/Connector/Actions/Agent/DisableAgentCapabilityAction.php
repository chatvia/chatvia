<?php

namespace Domain\Integration\Connector\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Disable a first-party capability for one agent — the inverse of
 * {@see EnableAgentCapabilityAction}.
 *
 * Disable = detach the pivot only. The account-level server row is left in
 * place (it may back other agents), so re-enabling is a pure re-attach.
 */
class DisableAgentCapabilityAction
{
    public function execute(Agent $agent, ConnectorData $capability): void
    {
        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $capability->slug)
            ->first();

        if ($server !== null) {
            $agent->detachConnector($server);
        }
    }
}
