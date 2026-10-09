<?php

namespace Domain\Conversation\Actions\Retention;

use Domain\Conversation\Enums\ToolApprovalStatus;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes tool-approval checkpoints that can never be resumed again. Each one
 * stores a whole conversation snapshot, so the table grows fast without this
 * sweep. Resolved checkpoints are kept for `$graceDays` (for debugging);
 * checkpoints past their expiry go at once.
 */
class PruneToolApprovalCheckpointsAction
{
    /**
     * @return int how many checkpoints were deleted
     */
    public function execute(int $graceDays): int
    {
        return ToolApprovalCheckpoint::query()
            ->where(fn (Builder $resolved) => $resolved
                ->where('status', '!=', ToolApprovalStatus::Pending)
                ->where('updated_at', '<', now()->subDays($graceDays)))
            ->orWhere('expires_at', '<', now())
            ->delete();
    }
}
