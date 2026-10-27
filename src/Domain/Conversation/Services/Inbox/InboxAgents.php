<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Inbox;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Handoff\HandoffAvailability;
use Illuminate\Support\Collection;

/**
 * The agents whose chats one staff member sees in the Inbox, and whether they
 * may join a chat there. The rules are the Chat Log's own (`agent.chat.view`
 * to read, `agent.chat.takeover` to reply), so the Inbox never shows or opens
 * more than the per-agent pages would.
 */
final readonly class InboxAgents
{
    public function __construct(
        private AgentAccessPolicy $access,
        private TeamPermissionPolicy $permissions,
        private HandoffAvailability $handoff,
    ) {}

    /**
     * @return Collection<int, Agent>
     */
    public function viewableBy(User $user): Collection
    {
        return $this->access->chattableAgents($user)
            ->filter(fn (Agent $agent): bool => $this->permissions->allowsOnAgent($user, $agent, 'agent.chat.view'))
            ->values();
    }

    /**
     * Whether this person may take over the agent's widget chats: live chat
     * is on for the agent (license key and toggle) and their role holds the
     * takeover permission. The surface check is the conversation's
     * ({@see Conversation::canBeTakenOverBy()}).
     */
    public function canReplyOn(User $user, Agent $agent): bool
    {
        return $this->handoff->isOnFor($agent)
            && $this->permissions->allowsOnAgent($user, $agent, 'agent.chat.takeover');
    }
}
