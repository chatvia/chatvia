<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use App\Models\Agent;
use App\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentFunctionDeleteController
{
    public function __invoke(Request $request, Agent $agent, AgentFunction $function): JsonResponse
    {
        abort_unless($function->agent_id === $agent->id, 404);
        abort_if($request->user()->cannot('delete', $function), 403);

        $function->delete();

        return response()->json(['ok' => true]);
    }
}
