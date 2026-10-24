<?php

namespace App\Http\Resources\Agent;

use App\Admin\Settings\AiProviderIcon;
use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Data\Resources\AgentPromptPickerData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Agent\Services\AgentModel;
use Domain\Conversation\Services\Voice\VoiceAvailability;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Data\LlmModelData;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ChatFileModel;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Uri;

/**
 * The props of the unified Setup page, drawn from many sources: the agent's
 * content/behaviour settings per surface, the prompt picker, the model
 * catalog, and this install's voice capabilities.
 */
final readonly class SetupPageProps
{
    public function __construct(
        private ChatterAuthTokenService $authTokens,
        private TeamPermissionPolicy $permissions,
        private AgentModel $agentModel,
        private TaskModels $models,
        private VoiceAvailability $voice,
        private Transcriber $transcriber,
        private ChatFileModel $chatFiles,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Agent $agent, User $user): array
    {
        $agent->loadMissing(['llm', 'agentPrompt']);

        // Its own model, or the default one while it has none.
        $selectedModel = $this->agentModel->for($agent)?->id;

        // The models that may be picked, plus the agent's own even when it
        // was turned off since.
        $catalog = Llm::catalog()
            ->filter(fn (Llm $model): bool => $model->isAvailable() || $model->id === $selectedModel)
            ->map(fn (Llm $model): LlmModelData => LlmModelData::fromLlmModel(
                $model,
                selected: $model->id === $selectedModel,
                providerIcon: AiProviderIcon::forKind($model->provider->kind),
            ))
            ->values()
            ->all();

        return [
            'agent' => AgentResourceData::fromModel($agent),
            'authToken' => $agent->is_public ? null : $this->authTokens->generateToken($agent, $user),
            'starters' => $agent->resolvedStarters(),
            // Widget chat-bubble prompts in editor shape ({ text, translations,
            // overrides }), coerced from the legacy plain-string list.
            'bubbles' => $agent->normalizedBubbles(),
            // Whether the agent has ingested knowledge-base content. Gates the
            // Setup → Starters "Generate with AI" button (which scans the chunks).
            'hasKnowledgeBase' => Chunk::query()->hasContentForAgent($agent),
            'prompts' => $user->workspace()->prompts()
                ->latest()
                ->get()
                ->map(fn (AgentPrompt $prompt): AgentPromptPickerData => AgentPromptPickerData::fromModel($prompt))
                ->all(),
            'hasPermission' => $this->permissions->allowsOnAgent($user, $agent, 'agent.setup'),

            // Shared content settings + their defaults — written across all surfaces.
            'appearanceSettings' => $agent->settings('appearance'),
            'defaultAppearance' => config('model-settings.defaults.'.Agent::class.'.appearance'),

            // Voice input and voice conversations (Settings → Composer
            // options): whether this install has speech to text and voice
            // calls set up, and the voice agents speak with unless they pick
            // their own.
            'voice' => [
                'inputAvailable' => $this->transcriber->isEnabled(),
                'available' => $this->voice->isSetUp(),
                'default' => RealtimeVoice::configured()->value,
                'choices' => array_column(RealtimeVoice::cases(), 'value'),
            ],

            // File uploads (Settings → Composer options): whether this
            // install allows chat file uploads and has a model that reads
            // them. Without it the switch shows off and is locked.
            'fileUploadsAvailable' => $this->chatFiles->isAvailable(),

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
            'defaultModel' => $this->models->find(LlmTask::Chat)?->id,
            'llmSettings' => $agent->llmSettings(),
            'defaultLlmSettings' => config('model-settings.defaults.'.Agent::class.'.llm_settings'),
            // The effort the agent picked, and whether the admin lets agents
            // reason at all (else the picker is shown off and locked).
            'reasoningEffort' => (string) $agent->settings('reasoning')->get('effort'),
            'reasoningAllowed' => (bool) config('chatvia.ai.reasoning.enabled'),

            'availableLanguages' => config('chatvia.languages'),

            // Custom domain (Hosted Chat only) — current value + CNAME target:
            // the app's own host, which serves every chat.
            'domain' => [
                'value' => $agent->domain,
                'cname_target' => (string) Uri::of((string) config('app.url'))->host(),
            ],
        ];
    }
}
