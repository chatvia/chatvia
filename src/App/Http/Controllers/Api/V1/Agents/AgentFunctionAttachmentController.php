<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Requests\Api\V1\Agents\AttachAgentFunctionRequest;
use App\Http\Resources\Api\V1\FunctionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attach / detach account-level functions to a specific agent.
 */
#[Group(name: 'Functions', weight: 60)]
class AgentFunctionAttachmentController
{
    /** @return array{data: list<FunctionData>} */
    public function index(Request $request, Agent $agent): array
    {
        return ['data' => $agent->functions()->get()
            ->map(fn (AgentFunction $f): FunctionData => FunctionData::fromModel($f))->all()];
    }

    /** @return array{data: FunctionData} */
    public function store(AttachAgentFunctionRequest $request, Agent $agent): array
    {
        $data = $request->toData($agent);
        $function = $this->resolveFunction($data->agent, $data->function_id);

        $data->agent->functions()->syncWithoutDetaching([$function->id]);

        return ['data' => FunctionData::fromModel($function->fresh())];
    }

    public function destroy(Request $request, Agent $agent, string $function): JsonResponse
    {
        $agent->functions()->detach($this->resolveFunction($agent, $function)->id);

        return response()->json(null, 204);
    }

    private function resolveFunction(Agent $agent, string $externalId): AgentFunction
    {
        return AgentFunction::query()
            ->where('user_id', $agent->user_id)
            ->where('external_id', $externalId)
            ->firstOrFail();
    }
}
