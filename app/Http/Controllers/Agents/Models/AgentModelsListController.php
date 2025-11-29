<?php

namespace App\Http\Controllers\Agents\Models;

use App\Data\Chat\LlmModelData;
use App\Data\Resources\Agent\AgentLlmDefaultSettingsData;
use App\Data\Resources\Agent\AgentLlmSettingsData;
use App\Data\Resources\Agent\AgentModelsResourceData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentModelsListController
{
    public function __invoke(Agent $agent, Request $request): AgentModelsResourceData
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

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
}
