<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use Domain\Agents\Actions\DetachFunctionFromAgentAction;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
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
