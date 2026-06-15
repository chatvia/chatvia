<?php

namespace App\Http\Controllers\Agent\Guardrail;

use App\Http\Requests\Agent\Guardrail\SaveGuardrailSettingsRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Models\GuardrailEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent AI-safety guardrails: the configuration form (PII / injection /
 * topic / toxicity toggles + blocked topics + allowed scope + refusal message)
 * and the immutable trigger log. `show` needs `agent.guardrails.view`; `update`
 * needs `agent.guardrails.manage` (enforced on the routes).
 */
class AgentGuardrailController
{
    public function show(Request $request, Agent $agent): Response
    {
        $settings = $agent->settings('guardrails');

        // Guardrails are a Standard-tier capability. Below it the page still
        // renders so the Vue LockedFeature card can show the upgrade prompt, but
        // we skip the trigger-log query and ship no activity data.
        $triggers = $request->user()->hasCapability(CapabilityEnum::GUARDRAILS)
            ? GuardrailEvent::query()
                ->forAgent($agent->id)
                ->with(['user', 'agent:id,external_id'])
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (GuardrailEvent $e): array => $e->toLogArray())
            : ['data' => []];

        return Inertia::render('agents/Guardrails/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => [
                'enabled' => (bool) $settings->get('enabled', false),
                'filters' => array_values((array) $settings->get('filters', PiiFilterType::defaults())),
                'injection' => (bool) $settings->get('injection', true),
                'toxicity' => (bool) $settings->get('toxicity', true),
                'blocked_topics' => array_values((array) $settings->get('blocked_topics', [])),
                'allowed_scope' => (string) $settings->get('allowed_scope', ''),
                'refusal_message' => (string) $settings->get('refusal_message', ''),
            ],
            'availableFilters' => PiiFilterType::catalog(),
            'defaultFilters' => PiiFilterType::defaults(),
            'triggers' => $triggers,
            'guardTypes' => ['prompt_injection', 'pii', 'blocked_topic', 'toxicity'],
            'outcomes' => ['blocked', 'redacted', 'flagged'],
        ]);
    }

    public function update(SaveGuardrailSettingsRequest $request, Agent $agent): RedirectResponse
    {
        foreach ($request->toData()->toSettings() as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: 'guardrails');
        }

        return back();
    }
}
