<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use Domain\Agents\Actions\ListAgentFunctionsAction;
use Domain\Agents\Data\Resources\AgentFunctionsListResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentFunctionsListController
{
    public function __invoke(Request $request, Agent $agent, ListAgentFunctionsAction $list): AgentFunctionsListResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return $list->execute($request->user(), $agent);
    }
}
