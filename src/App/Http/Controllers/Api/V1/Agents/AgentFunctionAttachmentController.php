<?php

namespace App\Http\Controllers\Api\V1\Agents;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Agent Function Attachments
 *
 * Attach / detach account-level functions to a specific agent. Function
 * definitions are managed via `/api/v1/functions` — this endpoint just
 * controls which agents can call which.
 */
class AgentFunctionAttachmentController
{
    /**
     * List attached functions
     */
    public function index(Request $request, Agent $agent): JsonResponse
    {
        $functions = $agent->functions()->get();

        return response()->json([
            'data' => $functions->map(fn (AgentFunction $f): array => $this->transform($f))->all(),
        ]);
    }

    /**
     * Attach function
     *
     * Body: `{ function_id: string }`. Idempotent — re-attaching is a no-op.
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'function_id' => ['required', 'string'],
        ]);

        $function = $this->resolveFunction($agent, $validated['function_id']);

        $agent->functions()->syncWithoutDetaching([$function->id]);

        return response()->json(['function' => $this->transform($function->fresh())], 201);
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

    private function transform(AgentFunction $f): array
    {
        return [
            'id' => $f->external_id,
            'name' => $f->name,
            'description' => $f->description,
            'enabled' => $f->enabled,
            'created_at' => $f->created_at?->toIso8601String(),
        ];
    }
}
