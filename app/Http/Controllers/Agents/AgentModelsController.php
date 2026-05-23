<?php

namespace App\Http\Controllers\Agents;

use App\Data\Chat\LlmModelData;
use App\Data\Resources\Agent\AgentLlmDefaultSettingsData;
use App\Data\Resources\Agent\AgentLlmSettingsData;
use App\Data\Resources\Agent\AgentModelsResourceData;
use App\Models\Agent;
use App\Models\Llm;
use Illuminate\Http\Request;

class AgentModelsController
{
    public function index(Request $request, Agent $agent): AgentModelsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $agentLlms = $agent->llms;

        $models = $request->user()->formattedLlms()->map(function (LlmModelData $llm) use ($agentLlms) {
            $llm->is_selected = $agentLlms->where('external_name', $llm->id)->first() !== null;

            return $llm;
        });

        return new AgentModelsResourceData(
            models: $models->all(),
            settings: AgentLlmSettingsData::fromArray($agent->llmSettings()),
            default_settings: AgentLlmDefaultSettingsData::defaults(),
        );
    }

    public function toggle(Request $request, Agent $agent): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $request->validate([
            'model_id' => ['required', 'exists:llms,external_name'],
            'status' => ['required', 'boolean'],
        ]);

        $modelId = Llm::query()->where('external_name', $request->input('model_id'))->valueOrFail('id');

        if ($request->boolean('status')) {
            $agent->llms()->attach($modelId);
        } else {
            $agent->llms()->detach($modelId);
        }
    }
}
