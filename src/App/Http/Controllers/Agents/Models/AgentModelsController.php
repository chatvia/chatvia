<?php

namespace App\Http\Controllers\Agents\Models;

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

        $agentLlms = $agent->resolvedLlms();

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
