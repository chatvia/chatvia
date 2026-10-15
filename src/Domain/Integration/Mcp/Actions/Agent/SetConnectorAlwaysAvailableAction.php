<?php

namespace Domain\Integration\Mcp\Actions\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Data\Agent\SetConnectorAlwaysAvailableData;
use Domain\Integration\Mcp\Exceptions\ConnectorNotAttachedException;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Marks a connector attached to an agent as "always on": its tools ship on
 * every turn, bypassing the relevance router. There is no cap on how many
 * tools the always-on connectors may ship together; the workspace decides.
 */
class SetConnectorAlwaysAvailableAction
{
    public function execute(Agent $agent, AgentMcpServer $server, SetConnectorAlwaysAvailableData $data): void
    {
        if (! $agent->mcpServers()->whereKey($server->id)->exists()) {
            throw ConnectorNotAttachedException::toAgent((string) $server->slug, $agent->id);
        }

        $agent->mcpServers()->updateExistingPivot($server->id, ['always_available' => $data->always_available]);
    }
}
