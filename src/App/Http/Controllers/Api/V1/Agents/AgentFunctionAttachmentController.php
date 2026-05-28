<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Resources\Api\V1\FunctionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attach / detach account-level functions to a specific agent. Function
 * definitions are managed via `/api/v1/functions` — this endpoint just
 * controls which agents can call which.
 */
#[Group(name: 'Functions', weight: 60)]
class AgentFunctionAttachmentController
{
    /**
     * List attached functions
     *
     * @return array{data: list<FunctionData>}
     */
    public function index(Request $request, Agent $agent): array
    {
        $functions = $agent->functions()->get();

        return ['data' => $functions->map(fn (AgentFunction $function): FunctionData => FunctionData::fromModel($function))->all()];
    }

    /**
     * Attach function
     *
     * Body: `{ function_id: string }`. Idempotent — re-attaching is a no-op.
     *
     * @return array{data: FunctionData}
     */
    public function store(Request $request, Agent $agent): array
    {
        $validated = $request->validate([
            'function_id' => ['required', 'string'],
        ]);

        $function = $this->resolveFunction($agent, $validated['function_id']);

        $agent->functions()->syncWithoutDetaching([$function->id]);

        return ['data' => FunctionData::fromModel($function->fresh())];
    }

    /**
     * Detach function
     */
    public function destroy(Request $request, Agent $agent, string $function): JsonResponse
    {
        $resolved = $this->resolveFunction($agent, $function);

        $agent->functions()->detach($resolved->id);

        return response()->json(null, 204);
    }

    private function resolveFunction(Agent $agent, string $externalId): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = AgentFunction::query()
            ->where('user_id', $agent->user_id)
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $function;
    }
}
