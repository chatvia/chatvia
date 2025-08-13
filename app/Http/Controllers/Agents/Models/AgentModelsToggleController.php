<?php

namespace App\Http\Controllers\Agents\Models;

use App\Models\Agent;
use App\Models\Llm;
use Illuminate\Http\Request;

class AgentModelsToggleController
{
    public function __invoke(Agent $agent, Request $request)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $request->validate([
            'model_id' => ['required', 'exists:llms,external_name'],
            'status' => ['required', 'boolean'],
        ]);

        /** @var bool $status */
        $status = $request->status;

        $modelId = Llm::query()->where('external_name', $request->model_id)->valueOrFail('id');

        if ($status) {
            $agent->llms()->attach($modelId);
        } else {
            $agent->llms()->detach($modelId);
        }
    }
}
