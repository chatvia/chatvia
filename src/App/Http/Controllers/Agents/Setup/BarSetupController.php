<?php

namespace App\Http\Controllers\Agents\Setup;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Data\LlmModelData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setup → Bar surface. The Bar is an embeddable cmd-K style launcher that
 * opens the agent in a focused chat overlay. It carries its own complete
 * configuration — text, colors, a single model, the keyboard trigger, and
 * the on-page placement — independent from Hosted Chat and Widget.
 */
class BarSetupController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if(! $request->user()->can('view', $agent), 404);

        $settings = $agent->settings('bar');
        $selectedModel = $settings->get('model');

        // The model picker on Bar is single-select — admins pick one of the
        // user's available LLMs. `is_selected` is set per row so the picker
        // can highlight the current choice without an extra round-trip.
        $catalog = $request->user()->formattedLlms()->map(function (LlmModelData $llm) use ($selectedModel) {
            $llm->is_selected = $llm->id === $selectedModel;

            return $llm;
        })->values()->all();

        return Inertia::render('agents/setup/Bar', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => $settings,
            'defaultSettings' => config('model-settings.defaults.'.Agent::class.'.bar'),
            'catalog' => $catalog,
            'selectedModel' => $selectedModel,
            'embedScriptUrl' => config('widget.script_url_embed'),
            'hasPermission' => has_permission_to_agent($agent, 'agent.setup'),
        ]);
    }
}
