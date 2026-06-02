<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Test-invoke a function. Currently a 501 stub — full HTTP dispatch lands later.
 */
class AgentFunctionTestController
{
    public function __invoke(Request $request, Agent $agent, AgentFunction $function): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 403);
        abort_unless($function->user_id === $agent->user_id, 404);
        abort_unless($agent->functions()->where('functions.id', $function->id)->exists(), 404, 'Function is not attached to this agent.');

        return response()->json([
            'message' => 'Webhook invocation is not implemented yet (arriving in Slice 3).',
        ], 501);
    }
}
