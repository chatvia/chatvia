<?php

namespace App\Http\Controllers\Agents\Setup;

use App\Auth\Services\ChatterAuthTokenService;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentPrompt;
use Domain\Chat\Data\LlmModelData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified Setup for every surface. The same content settings (text, persona,
 * starters, model, branding) apply across Hosted Chat, Widget, and Bar — the
 * surface-tab in the Vue page is purely a preview-switcher. The page does
 * receive each surface's behaviour-only settings (Widget position/sites,
 * Bar trigger/placement, Hosted Chat iframe options) because the Behavior +
 * Embed sections swap their fields depending on the active preview tab.
 */
class SetupController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokenService $authTokenService): Response
    {
        abort_if(! $request->user()->can('view', $agent), 404);

        $agent->loadMissing(['llm', 'agentPrompt']);

        $prompts = $request->user()->prompts()
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $p): array => [
                'id' => $p->external_id,
                'name' => $p->name,
                'prompt' => $p->prompt,
            ])
            ->all();

        $selectedModel = $agent->llm?->external_name;

        $catalog = $request->user()->formattedLlms()->map(function (LlmModelData $llm) use ($selectedModel) {
            $llm->is_selected = $llm->id === $selectedModel;

            return $llm;
        })->values()->all();

        $authToken = $agent->is_public ? null : $authTokenService->generateToken($agent, $request->user());

        return Inertia::render('agents/setup/Setup', [
            'agent' => AgentResourceData::fromModel($agent),
            'authToken' => $authToken,
            'starters' => $agent->resolvedStarters(),
            'prompts' => $prompts,
            'hasPermission' => has_permission_to_agent($agent, 'agent.setup'),

            // Shared content settings + their defaults — written across all surfaces.
            'appearanceSettings' => $agent->settings('appearance'),
            'defaultAppearance' => config('model-settings.defaults.'.Agent::class.'.appearance'),

            // Per-surface behaviour settings (Behavior + Embed sections swap on preview tab).
            'widgetSettings' => $agent->settings('widget'),
            'defaultWidget' => config('model-settings.defaults.'.Agent::class.'.widget'),
            'barSettings' => $agent->settings('bar'),
            'defaultBar' => config('model-settings.defaults.'.Agent::class.'.bar'),
            'embedSettings' => $agent->settings('embed'),
            'defaultEmbed' => config('model-settings.defaults.'.Agent::class.'.embed'),

            // Single-model picker + hyperparameters.
            'catalog' => $catalog,
            'selectedModel' => $selectedModel,
            'llmSettings' => $agent->llmSettings(),
            'defaultLlmSettings' => config('model-settings.defaults.'.Agent::class.'.llm_settings'),

            // Embed snippet helpers (Widget/Bar render their script tag in the Embed section).
            'embedScriptUrl' => config('widget.script_url_embed'),
            'availableLanguages' => config('widget.available_languages'),
        ]);
    }
}
