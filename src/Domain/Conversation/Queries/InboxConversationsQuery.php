<?php

declare(strict_types=1);

namespace Domain\Conversation\Queries;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The Inbox: one operator's live list across every agent they may view.
 * Chats waiting for a human come first, then the ones this operator holds,
 * then everyone else online, by the visitor's latest activity.
 */
final readonly class InboxConversationsQuery
{
    /** The list shows at most this many conversations. */
    public const int LIMIT = 100;

    /** A request for a human stays in the Inbox this long after the visitor's last activity. */
    public const int WAITING_WINDOW_HOURS = 24;

    /**
     * @param  SupportCollection<int, Agent>  $agents
     * @return Collection<int, Conversation>
     */
    public function get(SupportCollection $agents, User $operator): Collection
    {
        if ($agents->isEmpty()) {
            return new Collection;
        }

        return Conversation::query()
            ->whereIn('agent_id', $agents->pluck('id'))
            ->onlyVisible()
            ->inInboxOf($operator->id, self::WAITING_WINDOW_HOURS)
            ->with(['agent', 'session.user', 'session.chatter', 'session.country', 'lead', 'handoffUser'])
            ->withLastMessage()
            ->orderByRaw(
                'CASE WHEN handoff_status = ? THEN 0 WHEN handoff_status = ? AND handoff_user_id = ? THEN 1 ELSE 2 END',
                [HandoffStatus::Pending->value, HandoffStatus::Human->value, $operator->id],
            )
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * How many chats wait for a human: the Inbox link's badge.
     *
     * @param  SupportCollection<int, Agent>  $agents
     */
    public function waitingCount(SupportCollection $agents): int
    {
        if ($agents->isEmpty()) {
            return 0;
        }

        return Conversation::query()
            ->whereIn('agent_id', $agents->pluck('id'))
            ->onlyVisible()
            ->where('handoff_status', HandoffStatus::Pending)
            ->where('last_activity_at', '>=', now()->subHours(self::WAITING_WINDOW_HOURS))
            ->count();
    }
}
