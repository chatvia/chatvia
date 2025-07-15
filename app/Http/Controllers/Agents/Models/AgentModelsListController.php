<?php

namespace App\Http\Controllers\Agents\Models;

use App\Data\Chat\LlmModelData;
use App\Models\Agent;
use Illuminate\Http\Request;

final class AgentModelsListController
{
    public function __invoke(Agent $agent, Request $request)
    {
        $agentLlms = $agent->llms;

        $models = $request->user()->llms()->map(function (LlmModelData $llm) use ($agentLlms) {
            $llm->is_selected = $agentLlms->where('external_name', $llm->id)->first() !== null;

            return $llm;
        });

        return [
            'models' => $models->toArray(),
            'settings' => $agent->llmSettings(),
            'default_settings' => [
                'temperature' => 1.0,
                'top_k' => 0,
                'top_p' => 1.0,
                'presence_penalty' => 0.0,
                'frequency_penalty' => 0.0,
            ],
        ];
    }
}
