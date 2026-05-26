<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\AgentFunctionData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Returns every function the user owns, each tagged with its attachment
 * state for THIS agent. Mirrors the connectors list shape so the agent
 * UI is a uniform "toggle to attach/detach" surface — creation/editing
 * happens in Settings → Functions.
 */
class AgentFunctionsListController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var User $user */
        $user = $request->user();

        $functions = $user->functions()
            ->orderBy('name')
            ->get();

        $attachedIds = DB::table('agent_function')
            ->where('agent_id', $agent->id)
            ->pluck('function_id')
            ->flip();

        $rows = $functions->map(fn (AgentFunction $fn): array => [
            ...AgentFunctionData::fromModel($fn)->toArray(),
            'is_attached' => $attachedIds->has($fn->id),
        ]);

        return response()->json(['data' => $rows]);
    }
}
