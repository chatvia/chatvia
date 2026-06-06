<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
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
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless($agent->ownsFunction($function), 404);

        $agent->detachFunction($function);

        return response()->json(['ok' => true]);
    }
}
