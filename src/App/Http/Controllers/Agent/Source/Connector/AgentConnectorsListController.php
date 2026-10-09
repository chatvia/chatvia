<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\ListAgentConnectorsAction;
use Domain\Integration\Connector\Data\Agent\AgentConnectorsResourceData;

/**
 * JSON for the agent's Connectors page: the catalog with this agent's state
 * per connector, plus the workspace's custom servers.
 */
class AgentConnectorsListController
{
    public function __invoke(Agent $agent, ListAgentConnectorsAction $list): AgentConnectorsResourceData
    {
        return $list->execute($agent);
    }
}
