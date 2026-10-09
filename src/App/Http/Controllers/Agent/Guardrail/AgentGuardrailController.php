<?php

namespace App\Http\Controllers\Agent\Guardrail;

use App\Http\Requests\Agent\Guardrail\SaveGuardrailSettingsRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Billing\Enums\Capability;
use Domain\Guardrail\Actions\SaveGuardrailSettingsAction;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardType;
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
        // Guardrails are a Standard-tier capability. Below it the page still
        // renders so the Vue LockedFeature card can show the upgrade prompt, but
        // we skip the trigger-log query and ship no activity data.
        $triggers = $request->user()->hasCapability(Capability::Guardrails)
            ? GuardrailEvent::query()
                ->forAgent($agent->id)
                ->with(['user', 'agent:id,external_id'])
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (GuardrailEvent $event): array => $event->toLogArray())
            : ['data' => []];

        return Inertia::render('agents/Guardrails/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => GuardrailSettingsData::fromAgent($agent)->toSettings(),
            'availableFilters' => PiiFilterType::catalog(),
            'defaultFilters' => PiiFilterType::defaults(),
            'triggers' => $triggers,
            'guardTypes' => array_column(GuardType::cases(), 'value'),
            'outcomes' => array_column(GuardOutcome::cases(), 'value'),
        ]);
    }

    public function update(SaveGuardrailSettingsRequest $request, Agent $agent, SaveGuardrailSettingsAction $save): RedirectResponse
    {
        $save->execute($agent, $request->toData());

        return back();
    }
}
