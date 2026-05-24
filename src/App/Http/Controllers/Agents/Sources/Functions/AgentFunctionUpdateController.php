<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use Domain\Agents\Data\Resources\AgentFunctionData;
use App\Http\Requests\Agents\Functions\AgentFunctionRequest;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;

class AgentFunctionUpdateController
{
    public function __invoke(AgentFunctionRequest $request, Agent $agent, AgentFunction $function): JsonResponse
    {
        abort_unless($function->agent_id === $agent->id, 404);

        $data = $request->validated();
        $data['webhook_headers'] = array_values($data['webhook_headers'] ?? []);

        $function->fill($data);
        $function->save();

        return response()->json([
            'data' => AgentFunctionData::fromModel($function),
        ]);
    }
}
