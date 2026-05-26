<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentFunctionTestController
{
    /**
     * Test-invoke a function with user-supplied args. Slice 1 returns
     * 501 — the real HTTP dispatcher arrives in Slice 3.
     */
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
