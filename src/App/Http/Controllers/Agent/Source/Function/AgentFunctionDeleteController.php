<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Function\Actions\DetachFunctionFromAgentAction;
use Domain\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Detaches a function from this agent (pivot delete). The function row
 * itself lives in Settings → Functions and is unaffected. Route name
 * stays `sources.functions.delete` for backward compatibility with the
 * frontend; semantically it's now a detach.
 */
class AgentFunctionDeleteController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        AgentFunction $function,
        DetachFunctionFromAgentAction $detach,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 403);
        abort_unless($function->user_id === $agent->user_id, 404);

        $detach->execute($agent, $function);

        return response()->json(['ok' => true]);
    }
}
