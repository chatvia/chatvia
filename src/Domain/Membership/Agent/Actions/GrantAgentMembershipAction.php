<?php

declare(strict_types=1);

namespace Domain\Membership\Agent\Actions;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use DomainException;

/**
 * Grant an existing chatter access to an agent via the `agent_chatter` pivot.
 * Without the pivot row `Chatter::canAccessAgent()` rejects them. Idempotent —
 * `syncWithoutDetaching` is a no-op when the grant already exists.
 *
 * Shared by the agent-scoped Members tab and the workspace Members settings so
 * both grant access identically.
 */
class GrantAgentMembershipAction
{
    public function execute(Agent $agent, Chatter $chatter): void
    {
        // A chatter can only ever be granted its own workspace's agents.
        if ($chatter->workspace_id !== $agent->workspace_id) {
            throw new DomainException('A chatter can only be granted agents of its own workspace.');
        }

        $agent->chatters()->syncWithoutDetaching([$chatter->id => ['workspace_id' => $agent->workspace_id]]);
    }
}
