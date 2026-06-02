<?php

namespace Domain\Member\Actions;

use App\Workspace\Models\User;
use Domain\Agent\Models\Agent;

/**
 * Remove a chatter's access to an agent by deleting their row from the
 * `agent_user` pivot. The chatter user record itself is left intact (other
 * agents in the workspace may still grant them access).
 */
class RevokeAgentMembershipAction
{
    public function execute(Agent $agent, User $chatter): void
    {
        $agent->chatters()->detach($chatter->id);
    }
}
