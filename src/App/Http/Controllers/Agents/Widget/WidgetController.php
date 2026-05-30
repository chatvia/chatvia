<?php

namespace App\Http\Controllers\Agents\Widget;

use Domain\Agents\Data\Resources\AgentLlmDefaultSettingsData;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Data\LlmModelData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WidgetController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $agent->loadMissing('agentTemplate');

        $agentLlms = $agent->resolvedLlms();
        $selectedModels = $agentLlms->pluck('external_name')->all();
        $catalog = $request->user()->formattedLlms()->map(function (LlmModelData $llm) use ($selectedModels) {
            $llm->is_selected = in_array($llm->id, $selectedModels, true);

            return $llm;
        })->values()->all();

        return Inertia::render('agents/Widget', [
            'agent' => AgentResourceData::fromModel($agent),
            'widgetSettings' => $agent->settings('widget'),
            'appearanceSettings' => $agent->settings('appearance'),
            'embedScriptUrl' => config('widget.script_url_embed'),
            'availableLanguages' => config('widget.available_languages'),
            'starters' => $agent->resolvedStarters(),
            'has_permission' => has_permission_to_agent($agent, 'agent.setup'),
            'agent_template_id' => $agent->agentTemplate?->external_id,
            'attached_template' => $agent->agentTemplate
                ? ['id' => $agent->agentTemplate->external_id, 'name' => $agent->agentTemplate->name]
                : null,
            'catalog' => $catalog,
            'selectedModels' => $selectedModels,
            'llmSettings' => $agent->llmSettings(),
            'defaultLlmSettings' => AgentLlmDefaultSettingsData::defaults(),
        ]);
    }
}
