<?php

namespace Domain\Conversation\Actions\Retention;

use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes tool-approval checkpoints that can never be resumed again. Each one
 * stores a whole conversation snapshot, so the table grows fast without this
 * sweep. Resolved checkpoints are kept for `$graceDays` (for debugging);
 * checkpoints past their expiry go at once. The rows are large, so they go
 * in small batches.
 */
class PruneToolApprovalCheckpointsAction
{
    /**
     * @return int how many checkpoints were deleted
     */
    public function execute(int $graceDays, int $batchSize = 1000): int
    {
        $resolvedBefore = now()->subDays($graceDays);
        $deleted = 0;

        do {
            $stale = ToolApprovalCheckpoint::query()
                ->select('id')
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $resolved) => $resolved
                        ->where('status', '!=', ToolApprovalStatus::Pending)
                        ->where('updated_at', '<', $resolvedBefore))
                    ->orWhere('expires_at', '<', now()))
                ->limit($batchSize);

            $batch = ToolApprovalCheckpoint::query()->whereIn('id', $stale)->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
