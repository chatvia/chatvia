<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Catalog\ListConnectorToolsAction;
use Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The tool list of a connector attached to this agent.
 */
class AgentConnectorToolController
{
    public function __invoke(Agent $agent, string $slug, ListConnectorToolsAction $list): ConnectorToolsResourceData
    {
        $server = AgentMcpServer::query()
            ->attachedToAgent($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404);

        return $list->execute($server);
    }
}
