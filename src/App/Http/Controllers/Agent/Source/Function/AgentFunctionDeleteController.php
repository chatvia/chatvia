<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;

/**
 * Detaches a function from this agent (pivot delete). The function itself
 * lives in Settings → Functions and is unaffected. The route keeps its
 * `sources.functions.detach` name for the frontend.
 */
class AgentFunctionDeleteController
{
    public function __invoke(Agent $agent, AgentFunction $function): JsonResponse
    {
        abort_unless($agent->ownsFunction($function), 404);

        $agent->detachFunction($function);

        return response()->json(['ok' => true]);
    }
}
