<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Function\Actions\AttachFunctionToAgentAction;
use Domain\Function\Data\Resources\AgentFunctionAttachResultData;
use Domain\Function\Data\Resources\AgentFunctionData;
use Domain\Function\Models\AgentFunction;
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
        abort_unless($function->user_id === $agent->user_id, 404);

        $attached = $attach->execute($agent, $function);

        return new AgentFunctionAttachResultData(
            data: AgentFunctionData::fromModel($attached),
        );
    }
}
