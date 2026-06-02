<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Connector\Actions\Agent\ListAgentConnectorsAction;
use Domain\Connector\Data\Agent\AgentConnectorsResourceData;
use Illuminate\Http\Request;

class AgentConnectorsListController
{
    public function __invoke(Request $request, Agent $agent, ListAgentConnectorsAction $list): AgentConnectorsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return $list->execute($agent);
    }
}
