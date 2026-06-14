<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\ListAgentConnectorsAction;
use Domain\Integration\Connector\Data\Agent\AgentConnectorsResourceData;
use Illuminate\Http\Request;

class AgentConnectorsListController
{
    public function __invoke(Request $request, Agent $agent, ListAgentConnectorsAction $list): AgentConnectorsResourceData
    {
        return $list->execute($agent);
    }
}
