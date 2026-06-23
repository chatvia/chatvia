<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Data\LlmModelData;
use Domain\Prompt\Models\AgentPrompt;
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

        $domainContext = $this->buildDomainContext($request->user(), $agent);

        return Inertia::render('agents/setup/Setup', [
            'agent' => AgentResourceData::fromModel($agent),
            'authToken' => $authToken,
            'starters' => $agent->resolvedStarters(),
            // Candidate starters mined from the agent's knowledge-base chunks
            // (LLM-generated questions). Empty when the agent has no ingested
            // sources — the Setup UI hides the "Add from Knowledge Base" picker.
            'knowledgeQuestions' => Chunk::query()->questionsForAgent($agent->id),
            'prompts' => $prompts,
            'hasPermission' => has_permission_to_agent($agent, 'agent.setup'),
            'canUseHandoff' => $agent->team?->hasCapability(CapabilityEnum::HANDOFF) ?? false,
            // Whether the workspace may hide the "Powered by Chatvia" badge. Only
            // then does the Branding section expose the show/hide toggle; otherwise
            // the badge always shows and there's nothing to choose.
            'canHideBranding' => $agent->loadMissing('team.owner')->team?->owner?->canHideBranding() ?? false,

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

            // Agent-wide analytics opt-outs (Settings tab) — sentiment / tone /
            // topics on-off switches, default on.
            'analyticsSettings' => $agent->settings('analytics'),
            'defaultAnalytics' => config('model-settings.defaults.'.Agent::class.'.analytics'),

            // Single-model picker + hyperparameters.
            'catalog' => $catalog,
            'selectedModel' => $selectedModel,
            'llmSettings' => $agent->llmSettings(),
            'defaultLlmSettings' => config('model-settings.defaults.'.Agent::class.'.llm_settings'),

            // Embed snippet helpers (Widget/Bar render their script tag in the Embed section).
            'embedScriptUrl' => config('chatvia.widget.script_url_embed'),
            'availableLanguages' => config('chatvia.languages'),

            // Custom domain (Hosted Chat only) — quota + CNAME target.
            'domain' => $domainContext,
        ]);
    }

    /**
     * Build the per-agent custom-domain context: current value, the CNAME
     * target the user needs to point at, and the workspace owner's quota
     * (plan + custom-domain subscription). Used by the Domain section in
     * the Hosted Chat tab of the Setup page.
     *
     * @return array{value: ?string, cname_target: string, quota: array{total: int, used: int, available: int}, has_quota: bool}
     */
    private function buildDomainContext(User $user, Agent $agent): array
    {
        // `quota` and `agents()` on User already resolve to the workspace
        // owner (`workspace`), so quota maths is the same whether the
        // viewer is the owner or a team member.
        $total = (int) ($user->quota?->totalCustomDomainsLimit() ?? 0);

        // Agents (across the workspace) that have already claimed a custom
        // domain. Exclude the current agent so editing its own value doesn't
        // count against the available bucket.
        $used = (int) $user->agents()
            ->withCustomDomain()
            ->where('id', '!=', $agent->id)
            ->count();

        $cnameTarget = (string) parse_url($agent->permanent_url, PHP_URL_HOST);

        return [
            'value' => $agent->domain,
            'cname_target' => $cnameTarget,
            'quota' => [
                'total' => $total,
                'used' => $used,
                'available' => max(0, $total - $used),
            ],
            'has_quota' => $total > 0,
        ];
    }
}
