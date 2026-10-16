<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Domain\Security\Models\BlockedVisitor;

/**
 * Deletes visitor blocks that ended a while ago. An ended block already lets
 * the visitor chat; the rows are kept for some days so the list can still show
 * a block that just ended.
 */
class PruneExpiredVisitorBlocksAction
{
    /**
     * @return int how many blocks were deleted
     */
    public function execute(int $keepDays = 30): int
    {
        return BlockedVisitor::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subDays($keepDays))
            ->delete();
    }
}
