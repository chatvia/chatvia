<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Actions\AttachFunctionToAgentAction;
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
        AttachFunctionToAgentAction $attach,
    ): AgentFunctionAttachResultData {
        abort_if($request->user()->cannot('update', $agent), 403);
        abort_unless($agent->ownsFunction($function), 404);

        $attached = $attach->execute($agent, $function);

        return new AgentFunctionAttachResultData(
            data: AgentFunctionData::fromModel($attached),
        );
    }
}
