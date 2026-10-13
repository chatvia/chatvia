<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Retention;

use Domain\Conversation\Models\AgentActivity;

/**
 * Deletes governance audit rows (`agent_activity`) older than the retention
 * window, so the tool-call audit log stays bounded. Rows go in batches, so
 * one run never holds a huge delete; the BRIN index on `created_at` finds
 * them.
 */
class PruneAgentActivityAction
{
    /**
     * @return int how many rows were deleted
     */
    public function execute(int $retentionDays, int $batchSize = 10000): int
    {
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        do {
            $batch = AgentActivity::query()
                ->whereIn('id', AgentActivity::query()->select('id')->where('created_at', '<', $cutoff)->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
