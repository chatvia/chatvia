<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use App\Data\Resources\Agent\AgentFunctionData;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentFunctionsListController
{
    public function __invoke(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $functions = $agent->functions()
            ->orderBy('name')
            ->get()
            ->map(fn ($fn) => AgentFunctionData::fromModel($fn));

        return response()->json([
            'data' => $functions,
        ]);
    }
}
