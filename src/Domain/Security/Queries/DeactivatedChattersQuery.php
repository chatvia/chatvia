<?php

declare(strict_types=1);

namespace Domain\Security\Queries;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The switched-off chatters with access to an agent, most recently
 * deactivated first, for the Blocked lists.
 */
class DeactivatedChattersQuery
{
    /**
     * @return Collection<int, Chatter>
     */
    public function forAgent(Agent $agent, int $limit = 200): Collection
    {
        return Chatter::query()
            ->where('workspace_id', $agent->workspace_id)
            ->whereNotNull('deactivated_at')
            ->whereHas('agents', fn (Builder $query) => $query->whereKey($agent->id))
            ->orderByDesc('deactivated_at')
            ->limit($limit)
            ->get(['id', 'external_id', 'name', 'deactivation_reason', 'deactivated_at']);
    }
}
