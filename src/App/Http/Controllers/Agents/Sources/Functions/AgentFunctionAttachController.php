<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use Domain\Agents\Actions\AttachFunctionToAgentAction;
use Domain\Agents\Data\Resources\AgentFunctionAttachResultData;
use Domain\Agents\Data\Resources\AgentFunctionData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
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
