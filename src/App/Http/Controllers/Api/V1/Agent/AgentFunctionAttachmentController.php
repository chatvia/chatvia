<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Requests\Api\V1\Agent\AttachAgentFunctionRequest;
use App\Http\Resources\Api\V1\Tools\FunctionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\JsonResponse;

/**
 * Attach / detach account-level functions to a specific agent.
 */
#[Group(name: 'Functions', weight: 60)]
class AgentFunctionAttachmentController
{
    /**
     * List an agent's functions
     *
     * @return array{data: list<FunctionData>}
     */
    public function index(Agent $agent): array
    {
        return ['data' => $agent->functions()->get()
            ->map(fn (AgentFunction $f): FunctionData => FunctionData::fromModel($f))->all()];
    }

    /**
     * Attach function to agent
     *
     * @return array{data: FunctionData}
     */
    public function store(AttachAgentFunctionRequest $request, Agent $agent): array
    {
        $data = $request->toData($agent);
        $function = $this->resolveFunction($data->agent, $data->function_id);

        $data->agent->attachFunction($function);

        return ['data' => FunctionData::fromModel($function->fresh())];
    }

    /**
     * Detach function from agent
     */
    public function destroy(Agent $agent, string $function): JsonResponse
    {
        $agent->detachFunction($this->resolveFunction($agent, $function));

        return response()->json(null, 204);
    }

    private function resolveFunction(Agent $agent, string $externalId): AgentFunction
    {
        return AgentFunction::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('external_id', $externalId)
            ->firstOrFail();
    }
}
