<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Data\Resources\AgentFunctionAttachResultData;
use Domain\Integration\Function\Data\Resources\AgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\Request;

class AgentFunctionAttachController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        AgentFunction $function,
    ): AgentFunctionAttachResultData {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless($agent->ownsFunction($function), 404);

        $agent->attachFunction($function);

        return new AgentFunctionAttachResultData(
            data: AgentFunctionData::fromModel($function->fresh()),
        );
    }
}
