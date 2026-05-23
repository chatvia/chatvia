<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use App\Data\Resources\Agent\AgentFunctionData;
use App\Http\Requests\Agents\Functions\AgentFunctionRequest;
use App\Models\Agent;
use App\Models\AgentFunction;
use Illuminate\Http\JsonResponse;

class AgentFunctionStoreController
{
    public function __invoke(AgentFunctionRequest $request, Agent $agent): JsonResponse
    {
        $data = $request->validated();

        $function = new AgentFunction;
        $function->agent_id = $agent->id;
        $function->fill($this->normalize($data));
        $function->save();

        return response()->json([
            'data' => AgentFunctionData::fromModel($function),
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $data['webhook_headers'] = array_values($data['webhook_headers'] ?? []);

        return $data;
    }
}
