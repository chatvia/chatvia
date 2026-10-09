<?php

namespace Domain\Conversation\Actions\Retention;

use Domain\Conversation\Models\AgentActivity;

/**
 * Deletes governance audit rows (`agent_activity`) older than the retention
 * window, so the tool-call audit log stays bounded.
 */
class PruneAgentActivityAction
{
    /**
     * @return int how many rows were deleted
     */
    public function execute(int $retentionDays): int
    {
        return AgentActivity::query()
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}
