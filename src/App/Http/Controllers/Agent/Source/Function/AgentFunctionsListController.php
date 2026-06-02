<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Function\Actions\ListAgentFunctionsAction;
use Domain\Function\Data\Resources\AgentFunctionsListResourceData;
use Illuminate\Http\Request;

class AgentFunctionsListController
{
    public function __invoke(Request $request, Agent $agent, ListAgentFunctionsAction $list): AgentFunctionsListResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return $list->execute($request->user(), $agent);
    }
}
