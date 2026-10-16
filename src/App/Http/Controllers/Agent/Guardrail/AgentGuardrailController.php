<?php

namespace App\Http\Controllers\Agent\Guardrail;

use App\Http\Requests\Agent\Guardrail\SaveGuardrailSettingsRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Actions\SaveGuardrailSettingsAction;
use Domain\Guardrail\Data\GuardrailSettingsData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Guardrail\Queries\GuardrailOffendersQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent AI-safety guardrails: the configuration form (PII / injection /
 * topic / toxicity toggles + blocked topics + allowed scope + refusal message)
 * and the immutable trigger log, which the `guard_type`, `outcome` and
 * `visitor` (a visitor's fingerprint) query parameters filter (an unknown
 * value is ignored). Each abusive trigger says who caused it, whether they
 * are blocked and how often they tried lately ({@see GuardrailOffendersQuery}).
 * `show` needs `agent.guardrails.view`; `update` needs
 * `agent.guardrails.manage` (enforced on the routes).
 */
class AgentGuardrailController
{
    public function show(Request $request, Agent $agent, GuardrailOffendersQuery $offenders): Response
    {
        $guardType = GuardType::tryFrom($request->string('guard_type')->toString());
        $outcome = GuardOutcome::tryFrom($request->string('outcome')->toString());
        $visitor = $request->string('visitor')->toString();
        $visitor = preg_match('/^[0-9a-f]{64}$/', $visitor) === 1 ? $visitor : null;

        $page = GuardrailEvent::query()
            ->forAgent($agent->id)
            ->when($guardType, fn ($query, GuardType $type) => $query->where('guard_type', $type))
            ->when($outcome, fn ($query, GuardOutcome $value) => $query->where('outcome', $value))
            ->when($visitor, fn ($query, string $key) => $query->where('visitor_key', $key))
            ->with(['user', 'agent:id,external_id'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $offenderOf = $offenders->forEvents($page->getCollection(), (int) $agent->workspace_id);

        $triggers = $page->through(fn (GuardrailEvent $event): array => [
            ...$event->toLogArray(),
            'offender' => $offenderOf->get($event->id)?->toArray(),
        ]);

        return Inertia::render('agents/Guardrails/index', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => GuardrailSettingsData::fromAgent($agent)->toSettings(),
            'availableFilters' => PiiFilterType::catalog(),
            'defaultFilters' => PiiFilterType::defaults(),
            'triggers' => $triggers,
            'guardTypes' => array_column(GuardType::cases(), 'value'),
            'outcomes' => array_column(GuardOutcome::cases(), 'value'),
            'filters' => ['guard_type' => $guardType?->value, 'outcome' => $outcome?->value, 'visitor' => $visitor],
        ]);
    }

    public function update(SaveGuardrailSettingsRequest $request, Agent $agent, SaveGuardrailSettingsAction $save): RedirectResponse
    {
        $save->execute($agent, $request->toData());

        return back();
    }
}
