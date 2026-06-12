<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;

/**
 * Grant an existing chatter access to an agent via the `agent_user` pivot.
 * Without the pivot row `User::canAccessAgent()` rejects them. Idempotent —
 * `syncWithoutDetaching` is a no-op when the grant already exists.
 *
 * Shared by the agent-scoped Members tab and the workspace Members settings so
 * both grant access identically.
 */
class GrantAgentMembershipAction
{
    public function execute(Agent $agent, User $chatter): void
    {
        $agent->chatters()->syncWithoutDetaching([$chatter->id]);
    }
}
