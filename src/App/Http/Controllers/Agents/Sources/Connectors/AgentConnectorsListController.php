<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Models\Agent;
use Domain\Connectors\Actions\ListAgentConnectorsAction;
use Domain\Connectors\Data\AgentConnectorsResourceData;
use Illuminate\Http\Request;

class AgentConnectorsListController
{
    public function __invoke(Request $request, Agent $agent, ListAgentConnectorsAction $list): AgentConnectorsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return $list->execute($agent);
    }
}
