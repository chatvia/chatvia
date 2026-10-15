<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Agent\Services\AgentModel;
use Domain\Conversation\Services\Voice\VoiceAvailability;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Data\LlmModelData;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\TaskModels;
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
    public function __invoke(
        Request $request,
        Agent $agent,
        ChatterAuthTokenService $authTokenService,
        TeamPermissionPolicy $permissions,
        AgentModel $agentModel,
        TaskModels $models,
        VoiceAvailability $voice,
        Transcriber $transcriber,
    ): Response {
        $agent->loadMissing(['llm', 'agentPrompt']);

        $prompts = $request->user()->workspace()->prompts()
            ->latest()
            ->get()
            ->map(fn (AgentPrompt $p): array => [
                'id' => $p->external_id,
                'name' => $p->name,
                'prompt' => $p->prompt,
            ])
            ->all();

        // Its own model, or the default one while it has none.
        $selectedModel = $agentModel->for($agent)?->external_name;
        $defaultModel = $models->find(LlmTask::Chat)?->external_name;

        // The models that may be picked, plus the agent's own even when it
        // was turned off since.
        $catalog = Llm::catalog()->filter(fn (Llm $model): bool => $model->isAvailable() || $model->external_name === $selectedModel)->map(function (Llm $model) use ($selectedModel): LlmModelData {
            $llm = LlmModelData::fromLlmModel($model);
            $llm->is_selected = $llm->id === $selectedModel;

            return $llm;
        })->values()->all();

        $authToken = $agent->is_public ? null : $authTokenService->generateToken($agent, $request->user());

        return Inertia::render('agents/setup/Setup', [
            'agent' => AgentResourceData::fromModel($agent),
            'authToken' => $authToken,
            'starters' => $agent->resolvedStarters(),
            // Widget chat-bubble prompts in editor shape ({ text, translations,
            // overrides }), coerced from the legacy plain-string list.
            'bubbles' => $agent->normalizedBubbles(),
            // Whether the agent has ingested knowledge-base content. Gates the
            // Setup → Starters "Generate with AI" button (which scans the chunks).
            'hasKnowledgeBase' => Chunk::query()->hasContentForAgent($agent),
            'prompts' => $prompts,
            'hasPermission' => $permissions->allowsOnAgent($request->user(), $agent, 'agent.setup'),

            // Shared content settings + their defaults — written across all surfaces.
            'appearanceSettings' => $agent->settings('appearance'),
            'defaultAppearance' => config('model-settings.defaults.'.Agent::class.'.appearance'),

            // Voice input and voice conversations (Settings → Composer
            // options): whether this install has speech to text and voice
            // calls set up, and the voice agents speak with unless they pick
            // their own.
            'voice' => [
                'inputAvailable' => $transcriber->isEnabled(),
                'available' => $voice->isSetUp(),
                'default' => RealtimeVoice::configured()->value,
                'choices' => array_column(RealtimeVoice::cases(), 'value'),
            ],

            // Per-surface behaviour settings (the Behavior section swaps them on the preview tab).
            'widgetSettings' => $agent->settings('widget'),
            'defaultWidget' => config('model-settings.defaults.'.Agent::class.'.widget'),
            'barSettings' => $agent->settings('bar'),
            'defaultBar' => config('model-settings.defaults.'.Agent::class.'.bar'),
            'embedSettings' => $agent->settings('embed'),
            'defaultEmbed' => config('model-settings.defaults.'.Agent::class.'.embed'),

            // Agent-wide analytics opt-outs (Settings tab) — sentiment / tone /
            // topics on-off switches, default on.
            'analyticsSettings' => $agent->settings('analytics'),
            'defaultAnalytics' => config('model-settings.defaults.'.Agent::class.'.analytics'),

            // Single-model picker + hyperparameters.
            'catalog' => $catalog,
            'selectedModel' => $selectedModel,
            'defaultModel' => $defaultModel,
            'llmSettings' => $agent->llmSettings(),
            'defaultLlmSettings' => config('model-settings.defaults.'.Agent::class.'.llm_settings'),

            'availableLanguages' => config('chatvia.languages'),

            // Custom domain (Hosted Chat only) — current value + CNAME target:
            // the app's own host, which serves every chat.
            'domain' => [
                'value' => $agent->domain,
                'cname_target' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
        ]);
    }
}
