<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Services;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Whether the connector a capability works through is connected in the
 * agent's workspace: switched on and not failing. A capability without one
 * is always ready.
 */
class ConnectorPrerequisite
{
    public function isMet(Agent $agent, ConnectorData $capability): bool
    {
        if ($capability->requiresConnector === null) {
            return true;
        }

        return AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('connector_slug', $capability->requiresConnector)
            ->where('is_enabled', true)
            ->whereNull('last_error')
            ->exists();
    }
}
