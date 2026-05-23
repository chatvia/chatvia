<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use App\Data\Resources\Agent\AgentFunctionData;
use App\Models\Agent;
use App\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentFunctionToggleController
{
    public function __invoke(Request $request, Agent $agent, AgentFunction $function): JsonResponse
    {
        abort_unless($function->agent_id === $agent->id, 404);
        abort_if($request->user()->cannot('update', $function), 403);

        $function->enabled = ! $function->enabled;
        $function->save();

        return response()->json([
            'data' => AgentFunctionData::fromModel($function),
        ]);
    }
}
