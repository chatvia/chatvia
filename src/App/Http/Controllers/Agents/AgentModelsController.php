<?php

namespace App\Http\Controllers\Agents;

use Domain\Agents\Actions\ToggleAgentLlmAction;
use Domain\Agents\Data\Resources\AgentLlmDefaultSettingsData;
use Domain\Agents\Data\Resources\AgentLlmSettingsData;
use Domain\Agents\Data\Resources\AgentModelsResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Data\LlmModelData;
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

    public function toggle(Request $request, Agent $agent, ToggleAgentLlmAction $toggle): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $request->validate([
            'model_id' => ['required', 'exists:llms,external_name'],
            'status' => ['required', 'boolean'],
        ]);

        $toggle->execute(
            agent: $agent,
            modelExternalName: $request->string('model_id')->toString(),
            enabled: $request->boolean('status'),
        );
    }
}
