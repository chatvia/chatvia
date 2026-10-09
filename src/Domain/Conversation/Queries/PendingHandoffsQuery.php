<?php

namespace Domain\Conversation\Queries;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;

/**
 * The agent's conversations waiting for a human operator, most recently
 * requested first — the dashboard's handoff badge and list. Loads what the
 * list shows (visitor, captured contact, last message) up front.
 */
class PendingHandoffsQuery
{
    /** The list shows at most this many waiting conversations. */
    public const int LIMIT = 50;

    /**
     * @return Collection<int, Conversation>
     */
    public function get(Agent $agent): Collection
    {
        return Conversation::query()
            ->forAgent($agent->id)
            ->with(['session.user', 'lead', 'lastMessage'])
            ->where('handoff_status', HandoffStatus::Pending)
            ->where('is_visible', true)
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get();
    }
}
