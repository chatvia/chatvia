<?php

namespace Domain\Agents\Actions\Members;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;

/**
 * Grant an existing workspace chatter access to an agent by inserting their
 * row into the `agent_user` pivot. Idempotent: if the grant already exists
 * we skip the attach so the pivot's `created_at` stays stable.
 */
class GrantChatterAccessToAgentAction
{
    public function execute(Agent $agent, User $chatter): void
    {
        if ($agent->chatters()->whereKey($chatter->id)->exists()) {
            return;
        }

        $agent->chatters()->attach($chatter->id);
    }
}
