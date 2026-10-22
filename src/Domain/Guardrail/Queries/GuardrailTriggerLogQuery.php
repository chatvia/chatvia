<?php

declare(strict_types=1);

namespace Domain\Guardrail\Queries;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\GuardrailLogFiltersData;
use Domain\Guardrail\Models\GuardrailEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Filtered, newest-first page of an agent's immutable guardrail trigger log,
 * for the Guardrails page.
 */
class GuardrailTriggerLogQuery
{
    /**
     * @return LengthAwarePaginator<int, GuardrailEvent>
     */
    public function forAgent(Agent $agent, GuardrailLogFiltersData $filters, int $perPage = 20): LengthAwarePaginator
    {
        return GuardrailEvent::query()
            ->forAgent($agent->id)
            ->when($filters->guard_type, fn (Builder $query) => $query->where('guard_type', $filters->guard_type))
            ->when($filters->outcome, fn (Builder $query) => $query->where('outcome', $filters->outcome))
            // The workspace too, so the (workspace_id, visitor_key, created_at)
            // index finds the visitor's rows instead of walking the agent's log.
            ->when($filters->visitor, fn (Builder $query) => $query
                ->where('workspace_id', $agent->workspace_id)
                ->where('visitor_key', $filters->visitor))
            // Only what the row shows: the name and the photo behind the avatar.
            ->with(['user:id,name,profile_image_path', 'agent:id,external_id'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
