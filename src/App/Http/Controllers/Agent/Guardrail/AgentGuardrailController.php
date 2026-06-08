<?php

namespace App\Http\Controllers\Agent\Guardrail;

use App\Http\Requests\Agent\Guardrail\SaveGuardrailSettingsRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
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
        abort_if($request->user()->cannot('view', $agent), 404);

        $settings = $agent->settings('guardrails');

        $triggers = GuardrailEvent::query()
            ->forAgent($agent->id)
            ->with(['user', 'agent:id,external_id'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (GuardrailEvent $e): array => $e->toLogArray());

        return Inertia::render('agents/Guardrails/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => [
                'enabled' => (bool) $settings->get('enabled', false),
                'pii' => (bool) $settings->get('pii', true),
                'injection' => (bool) $settings->get('injection', true),
                'toxicity' => (bool) $settings->get('toxicity', true),
                'blocked_topics' => array_values((array) $settings->get('blocked_topics', [])),
                'allowed_scope' => (string) $settings->get('allowed_scope', ''),
                'refusal_message' => (string) $settings->get('refusal_message', ''),
            ],
            'triggers' => $triggers,
            'guardTypes' => ['prompt_injection', 'pii', 'blocked_topic', 'toxicity'],
            'outcomes' => ['blocked', 'redacted', 'flagged'],
        ]);
    }

    public function update(SaveGuardrailSettingsRequest $request, Agent $agent): RedirectResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        foreach ($request->toData()->toSettings() as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: 'guardrails');
        }

        return back();
    }
}
