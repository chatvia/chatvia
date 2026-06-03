<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;

/**
 * Grant an existing workspace chatter access to an agent by inserting their
 * row into the `agent_user` pivot. Idempotent: if the grant already exists
 * we skip the attach so the pivot's `created_at` stays stable.
 */
class GrantAgentMembershipAction
{
    public function execute(Agent $agent, User $chatter): void
    {
        if ($agent->chatters()->whereKey($chatter->id)->exists()) {
            return;
        }

        $agent->chatters()->attach($chatter->id);
    }
}
