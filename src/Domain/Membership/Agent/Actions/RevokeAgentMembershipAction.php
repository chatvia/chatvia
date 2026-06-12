<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;

/**
 * Revoke a chatter's access to an agent by removing the `agent_user` pivot row.
 * The chatter account itself is untouched. Idempotent — detaching a row that
 * isn't there is a no-op.
 *
 * Shared by the agent-scoped Members tab and the workspace Members settings so
 * both revoke access identically.
 */
class RevokeAgentMembershipAction
{
    public function execute(Agent $agent, User $chatter): void
    {
        $agent->chatters()->detach($chatter->id);
    }
}
